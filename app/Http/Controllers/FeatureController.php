<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganisationComponentsUpdateRequest;
use App\Http\Requests\OrganisationStatementImplementationUpdateRequest;
use App\Http\Requests\OrganisationTasksUpdateRequest;
use App\Models\Action;
use App\Models\Component;
use App\Models\Organisation;
use App\Models\Review;
use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeatureController extends Controller
{
    public function index()
    {
        $messages = __('messages');
        $components = Component::whereHas('statements.reviews', function (Builder $query) {
            $query->where('organisation_id', auth()->user()->organisation->id);
        })->get();
        $statements = auth()->user()->organisation->statements;
        $statements = $statements->map(function ($statement) {
            $statement->implementation = $statement->pivot->implementation;
            return $statement;
        })->makeVisible(['subcode', 'implementation']);
        $organisations = auth()->user()->organisation->organisations;

        return view('features', compact('messages', 'components', 'statements', 'organisations'));
    }

    public function updateImplementations(OrganisationStatementImplementationUpdateRequest $request)
    {
        $statements = $request->post('statements');
        $organisations = $request->post('organisations');

        foreach ($organisations as $organisationId) {
            $organisation = Organisation::find($organisationId);
            foreach ($statements as $statementId) {
                $statement = auth()->user()->organisation->statements()->where('statements.id', $statementId)->first();
                $organisation->statements()->syncWithoutDetaching([$statementId => ['implementation' => $statement->pivot->implementation]]);
            }
        }

        return ['success' => true];
    }

    public function updateTasks(OrganisationTasksUpdateRequest $request)
    {
        $organisations = $request->post('organisations');
        $tasks = $request->post('tasks');

        DB::transaction(function () use ($organisations, $tasks) {
            foreach ($organisations as $organisationId) {
                $organisation = Organisation::find($organisationId);
                $auditor = $organisation->users->where('role', 'auditor')->first();
                if ($auditor) {
                    foreach ($tasks as $taskId) {
                        $task = Task::find($taskId);
                        $taskStatus = TaskStatus::where('name_en', 'Pending')->first();
                        $actions = $task->actions;
                        $createdTask = Task::create([
                            'title_en' => $task['title_en'],
                            'title_se' => $task['title_se'],
                            'desc_en' => $task['desc_en'],
                            'desc_se' => $task['desc_se'],
                            'start' => $task['start'],
                            'end' => $task['end'],
                            'hours' => $task['hours'],
                            'task_status_id' => $taskStatus->id,
                            'created_by' => auth()->user()->id,
                            'assigned_to' => $auditor->id,
                        ]);

                        foreach ($actions as $action) {
                            $createdAction = Action::create([
                                'task_id' => $createdTask->id,
                                'action_type_id' => $action->action_type_id,
                                'action_status_id' => $action->action_status_id,
                            ]);

                            if ($action->actionType->model == 'component') {
                                $components = $action->components->pluck('id')->all();
                                $createdAction->components()->attach($components);
                            } elseif ($action->actionType->model == 'statement') {
                                $statements = $action->statements->pluck('id')->all();
                                $createdAction->statements()->attach($statements);
                            }
                        }
                    }
                }
            }
        });

        return ['success' => true];
    }

    public function updateComponents(OrganisationComponentsUpdateRequest $request)
    {
        $components = $request->post('components');
        $organisations = $request->post('organisations');

        foreach ($organisations as $organisationId) {
            $organisation = Organisation::find($organisationId);
            foreach ($components as $componentId) {
                $reviews = Review::whereRelation('statement', 'component_id', $componentId)
                    ->where('organisation_id', auth()->user()->organisation->id)
                    ->get();

                $reviews->each(function ($review) use ($organisation) {
                    $reviewExists = Review::where('organisation_id', $organisation['id'])
                        ->where('statement_id', $review['statement_id'])
                        ->exists();

                    if ($reviewExists) {
                        Review::where('organisation_id', $organisation['id'])
                            ->where('statement_id', $review['statement_id'])
                            ->update([
                                'user_id' => auth()->user()->id,
                                'review_status_id' => $review['review_status_id'],
                                'review' => $review['review'],
                            ]);
                    } else {
                        Review::insert(
                            [
                                'organisation_id' => $organisation['id'],
                                'statement_id' => $review['statement_id'],
                                'user_id' => auth()->user()->id,
                                'review_status_id' => $review['review_status_id'],
                                'review' => $review['review'],
                            ]
                        );
                    }
                });
            }
        }

        return ['success' => true];
    }
}