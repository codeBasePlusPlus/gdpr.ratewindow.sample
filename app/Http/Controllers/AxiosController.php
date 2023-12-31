<?php

namespace App\Http\Controllers;

use App\Http\Requests\AxiosOrganisationUpdateRequest;
use App\Http\Requests\OrganisationsComponentsPeriodsUpdateRequest;
use App\Http\Requests\OrganisationsKpicommentsStoreRequest;
use App\Http\Requests\OrganisationsPlanAuditorUpdateRequest;
use App\Http\Requests\OrganisationsStatementsDeedsUpdateAllRequest;
use App\Http\Requests\OrganisationsStatementsDeedsUpdateRequest;
use App\Http\Requests\OrganisationsStatementsPlansUpdateRequest;
use App\Http\Requests\OrganisationsStatementsReviewsUpdateRequest;
use App\Http\Requests\RiskCommentStoreRequest;
use App\Http\Requests\SanctionFileUploadRequest;
use App\Mail\InterviewStored;
use App\Models\Action;
use App\Models\ActionType;
use App\Models\Component;
use App\Models\Config;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Deed;
use App\Models\DeedHistory;
use App\Models\Dpa;
use App\Models\Faq;
use App\Models\Interview;
use App\Models\Kpi;
use App\Models\Kpicomment;
use App\Models\Link;
use App\Models\Organisation;
use App\Models\Period;
use App\Models\Plan;
use App\Models\Review;
use App\Models\ReviewStatus;
use App\Models\Risk;
use App\Models\RiskComment;
use App\Models\Sanction;
use App\Models\SanctionFile;
use App\Models\Statement;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Template;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AxiosController extends Controller
{
    public function actionTypes()
    {
        return ActionType::all();
    }

    public function components($locale)
    {
        return Component::orderBy('code')
            ->orderBy("name_$locale")
            ->get()
            ->makeHidden(['desc_en', 'desc_se', 'sort_order', 'created_at', 'updated_at']);
    }

    public function configs()
    {
        return Config::all();
    }

    /**
     * Return all countries along with dictionary
     *
     * Undocumented function long description
     *
     * @param string $locale app locale
     * @return Illuminate\Database\Eloquent\Collection
     **/
    public function countries($locale)
    {
        $countries = Country::all();
        App::setlocale($locale);
        $messages = Lang::get('messages');
        $r = ['countries' => $countries, 'messages' => $messages];
        $r = collect($r);
        return $r;
    }

    public function currencies()
    {
        $currencies = Currency::all();

        $currencies = $currencies->map(function ($currency) {
            $currency->updated_at_for_humans = $currency->updated_at->format('Y-m-d H:i:s');
            return $currency;
        });

        return ['currencies' => $currencies];
    }

    public function currenciesRatesUpdate($locale)
    {
        $currencies = Currency::all();
        $currencies = $currencies->pluck('symbol')->all();

        $rates = \AmrShawky\Currency::rates()
            ->latest()
            ->symbols($currencies)
            ->round(2)
            ->get();

        foreach ($rates as $symbol => $rate) {
            Currency::where('symbol', $symbol)
                ->update(['value' => $rate]);
        }
    }

    /**
     * Return all dpas along with dictionary
     *
     * Undocumented function long description
     *
     * @param string $locale app locale
     * @return Illuminate\Database\Eloquent\Collection
     **/
    public function dpas($locale)
    {
        $dpas = Dpa::all()->load('country')->makeVisible(['country', 'created_at_for_humans', 'name', 'url']);
        App::setlocale($locale);
        $messages = Lang::get('messages');
        $r = ['dpas' => $dpas, 'messages' => $messages];
        $r = collect($r);
        return $r;
    }

    public function faqs()
    {
        return Faq::all();
    }

    public function featuresTasks($locale, $year = null)
    {
        return Task::with('taskStatus')
            ->distinct()
            ->whereIn('created_by', auth()->user()->organisation->users->pluck('id'))
            ->when($year, function ($query) use ($year) {
                $since = Carbon::createFromDate($year, 1, 1);
                $until = Carbon::createFromDate($year, 12, 31);
                $query->whereDate('start', '>=', $since)
                    ->whereDate('start', '<=', $until);
            })->get();
    }

    public function featuresTasksYears()
    {
        return DB::table('tasks')
            ->selectRaw('YEAR(start) AS year')
            ->distinct()
            ->whereIn('created_by', auth()->user()->organisation->users->pluck('id'))
            ->orderBy('year')
            ->get()
            ->pluck('year')
            ->all();
    }
    /**
     * Resend emails to a +1 interviewees
     *
     * Undocumented function long description
     *
     * @param Type $var Description
     * @return type
     * @throws conditon
     **/
    public function interviewsResend($locale, Request $request)
    {
        $data = $request->all();
        // webform assume by default
        $interviews = Interview::whereIn('id', $data['ids'])->get();
        foreach ($interviews as $interview) {
            $user = User::where('id', $interview->interviewee)->first();
            $body = $data['body'];
            if (env('APP_ENV' == 'local')) {
                $email = 'janosaudron13@gmail.com';
            }
            if (env('APP_ENV' == 'production')) {
                $email = $user->email;
                if ($email == null) {
                    $email = 'fredrik@itsakerhetsbolaget.se';
                }
            }
            try {
                Mail::to($email)->send(new InterviewStored($user, $body));
                $count = intval($interview->emails);
                $count += 1;
                $interview->emails = $count;
                $interview->save();
            } catch (\Throwable $th) {
                throw $th;
            }
        }
        return response('success', 200);
    }
    /**
     * Update interview
     *
     * Axios call to update an interview
     *
     * @param Interview $interview Interview to be updated
     * @return Response
     **/
    public function interviewUpdate($locale, Request $request)
    {
        $interview = Interview::where('id', $request['id'])->first();
        // if statements are 0
        if (count($request['statements']) == 0) {
            // clear all statements
            try {
                $interview->statements()->detach();
                $interview->delete();
            } catch (\Throwable $th) {
                throw $th;
            }
        } else {
            // sync
            $statements = collect($request['statements'])->pluck('id')->toArray();
            try {
                $interview->statements()->sync($statements);
            } catch (\Throwable $th) {
                throw $th;
            }
        }
        return response('success', 200);
    }
    /**
     * Return all kpis along with dictionary
     *
     * Undocumented function long description
     *
     * @param string $locale app locale
     * @return Illuminate\Database\Eloquent\Collection
     **/
    public function kpis($locale)
    {
        $kpis = Kpi::all();
        App::setlocale($locale);
        $messages = Lang::get('messages');
        $r = ['kpis' => $kpis, 'messages' => $messages];
        $r = collect($r);
        return $r;
    }

    public function links()
    {
        return Link::all();
    }

    /**
     * Return the messages dictionaries
     *
     * no further desc
     *
     * @param String $var App locale
     * @return Illuminate\Database\Eloquent\Collection
     **/
    public function messages($locale)
    {
        App::setlocale($locale);
        $messages = Lang::get('messages');
        $r = ['messages' => $messages];
        $r = collect($r);
        return $r;
    }

    public function organisationsChange($locale, Organisation $organisation)
    {
        if ($organisation->id == auth()->user()->organisation->id || auth()->user()->organisation->organisations->contains(function ($subOrg) use ($organisation) {
            return $subOrg->id == $organisation->id;
        })) {
            session(['selected_org' => ['id' => $organisation->id, 'name' => $organisation->name]]);
        } else {
            session(['selected_org' => ['id' => auth()->user()->organisation->id, 'name' => auth()->user()->organisation->name]]);
        }

        return ['success' => true];
    }

    /**
     * Return the data about the organisation (and its suborganisations) relevant to the act page
     *
     * Undocumented function long description
     *
     * @param String $locale AppLocale
     * @return Illuminate\Database\Eloquent\Collection
     **/
    public function organisationsInsights($locale)
    {
        App::setlocale($locale);
        $messages = Lang::get('messages');
        $orgData = [];
        $org = Organisation::find(session('selected_org')['id']);
        $currentYear = Carbon::now()->format('Y');

        $years = $org->deedsYears();
        if ($years == []) {
            $years = [$currentYear];
        }
        if (!in_array($currentYear, $years)) {
            $years[] = $currentYear;
        }
        rsort($years);

        $data = [];
        foreach ($years as $year) {
            $data[$year] = [];
            $components = Component::all();
            foreach ($components as $comp) {
                $data[$year]['components'][] = $comp->code;
                $data[$year]['commitment'][] = $org->commitment;
                $data[$year]['mean'][] = $comp->statementMeanValue($org, $year);
                $data[$year]['codenames'][] = $comp->codeName;
                $name = mb_strlen($comp->{'name_' . App::currentLocale()}) > 16 ? mb_substr($comp->{'name_' . App::currentLocale()}, 0, 13) . '...' : $comp->{'name_' . App::currentLocale()};

                $comp->load('statements');
                $comp->statements->each(function ($statement) use ($org) {
                    $op = $statement->organisationPlan($org);
                    $statement->implementation = $op->implementation ?? '';
                    $od = $statement->organisationDeed($org);
                    $statement->deed = $od?->load('deedHistory')->makeVisible('deedHistory');
                    $statement->review = $statement->organisationReview($org);
                    $statement->makeVisible('implementation', 'deed', 'review');
                });

                $data[$year]['table'][] = ['id' => $comp->id, 'code' => $comp->code, 'name' => $name, 'desc' => $comp->{"desc_$locale"}, 'commitment' => $org->commitment, 'mean' => $comp->statementMeanValue($org, $year), 'fullname' => $comp->{'name_' . App::currentLocale()}, 'statements' => $comp->statements];
            }
            // Kpis
            $kpis = Kpi::all();
            foreach ($kpis as $kpi) {
                $orgKpiComments = $kpi->org_kpicomments($org);
                $orgKpiLast = $orgKpiComments->last();
                $target = '';
                $value = '';
                $comment = '';
                if ($orgKpiLast) {
                    $target = $orgKpiLast->target;
                    $value = $orgKpiLast->value;
                    $comment = $orgKpiLast->comment;
                }
                $data[$year]['kpis'][] = ['id' => $kpi->id, 'name' => $kpi->{'name_' . App::currentLocale()}, 'desc' => $kpi->{'desc_' . App::currentLocale()}, 'target' => $target, 'value' => $value, 'comment' => $comment];
            }
            // Risks
            $scatterRisks = $org->risks->sortBy('created_at');
            $data[$year]['risks']['datasets'] = [];
            foreach ($scatterRisks as $risk) {
                if (Carbon::parse($risk->created_at)->lessThanOrEqualTo(Carbon::create($year)->lastOfYear())) {
                    $r = $scatterRisks->filter(function ($item) use ($risk, $year) {
                        return ($item->consequence == $risk->consequence && $item->probability == $risk->probability && Carbon::parse($item->created_at)->lessThanOrEqualTo(Carbon::create($year)->lastOfYear()));
                    });
                    $r = 10 * count($r);
                    $data[$year]['risks']['datasets'][] = [
                        'label' => $risk->title,
                        'backgroundColor' => $risk->risk()['colour'],
                        'borderColor' => $risk->risk()['colour'],
                        'data' => [
                            ['x' => $risk->consequence, 'y' => $risk->probability, 'r' => $r],
                        ],
                        'count' => $r / 10,
                        'fs' => 11 + ($r / 10),
                    ];
                }
            }
            $data[$year]['risks']['legend'] = [
                ['text' => $messages['low'], 'colour' => '#28c76f'],
                ['text' => $messages['lowMed'], 'colour' => '#cab707'],
                ['text' => $messages['medium'], 'colour' => '#FF9F43'],
                ['text' => $messages['mediumHigh'], 'colour' => '#ff5f43'],
                ['text' => $messages['high'], 'colour' => '#EA5455'],

            ];
        }
        $orgData[] = ['name' => $org->name, 'data' => $data];
        // Localization
        $r = ['data' => $orgData, 'messages' => $messages];
        $r = collect($r);
        return $r;
    }

    /**
     * Show organisation do
     *
     * Retrieve all statements and return them as data in relation to organisations deeds on said statements if any
     *
     * @param String $locale
     * @return \Illuminate\Http\Response
     **/
    public function organisationsDo($locale, Action $action = null)
    {
        if ($action) {
            //TODO: check access

            if ($action->actionType->model == 'component') {
                $components = $action->components;
                $statements = $components->flatMap(function ($component) {
                    return $component->statements->sortBy('subcode', SORT_NATURAL)->makeVisible(['component', 'deed', 'implementation', 'plan', 'review', 'subcode']);
                });
            } elseif ($action->actionType->model == 'statement') {
                $statements = $action->statements->sortBy('subcode', SORT_NATURAL)->makeVisible(['component', 'deed', 'implementation', 'plan', 'review', 'subcode']);
            }
        } else {
            $statements = Statement::all()->sortBy('subcode', SORT_NATURAL)->load('component')->makeVisible(['component', 'deed', 'implementation', 'plan', 'review', 'subcode']);
        }

        $org = Organisation::find(session('selected_org')['id']);

        foreach ($statements as $statement) {
            $op = $statement->component->organisationPeriod($org);
            $statement->component->makeVisible(['organisation_period']);
            $statement->component->organisation_period = $op;
            //$statement->plan = null;
            $statement->implementation = null;
            $op = $statement->organisationPlan($org);
            if ($op) {
                //$statement->plan = $op->plan;
                $statement->implementation = $op->implementation;
            }
            $statement->deed = $statement->organisationDeed($org);
            $statement->deed = $statement->deed?->load('deedHistory')->makeVisible('deedHistory');
            $statement->review = $statement->organisationReview($org);
            // new badge
            if ($statement->deed && $statement->review) {
                $statement->review->makeVisible(['updated_at_for_humans', 'new']);
                $statement->review->new = false;
                if ((Carbon::parse($statement->deed->updated_at) < Carbon::parse($statement->review->updated_at)) && $statement->review->accepted != true) {
                    $statement->review->new = true;
                } else {
                    $statement->review->new = false;
                }
                $statement->deed->makeVisible(['updated_at_for_humans']);
            }
        };
        App::setlocale($locale);
        $messages = Lang::get('messages');
        $r = ['statements' => $statements, 'messages' => $messages];
        $r = collect($r);
        return $r;
    }

    /**
     * update the period of the component in relation to an organisation, e.g: set P1 to period Third Quarter for Organisation x
     */
    public function organisationsComponentsPeriodsUpdate(OrganisationsComponentsPeriodsUpdateRequest $request)
    {
        $data = $request->validated();
        $o = Organisation::find(session('selected_org')['id']);
        $role = Auth::user()->role;
        if (!(in_array($role, ['user', 'auditor']))) {
            $role = 'user';
        }
        // find if this component has a period
        $x = DB::table('component_organisation')->where('organisation_id', $o->id)->where('component_id', $data['component_id'])->where('role', $role)->first();
        if ($x) {
            DB::table('component_organisation')->where('organisation_id', $o->id)->where('component_id', $data['component_id'])->where('role', $role)->update(['period_id' => $data['period_id'], 'updated_at' => Carbon::now()]);
        } else {
            $o->components()->attach([$data['component_id'] => ['period_id' => $data['period_id'], 'role' => $role, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]]);
        }
        return response('success', 200);
    }

    /**
     * Store a kpicomment by an organisation user in relation to a kpi
     *
     *
     * @param Type $var Description
     * @return type
     * @throws conditon
     **/
    public function organisationsKpicommentsStore($locale, OrganisationsKpicommentsStoreRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = Auth::user()->id;
        Kpicomment::create($data);
        return response('success', 200);
    }

    /**
     * Return all kpis and inject kpicomments relevant to said organisation along with the dictionary
     *
     * Undocumented function long description
     *
     * @param string $locale App Locale
     * @return Illuminate\Database\Eloquent\Collection
     **/
    public function organisationsKpis($locale)
    {
        $org = Organisation::find(session('selected_org')['id']);
        $kpis = $org->kpis();
        App::setlocale($locale);
        $messages = Lang::get('messages');
        $r = ['kpis' => $kpis, 'messages' => $messages];
        $r = collect($r);
        return $r;
    }

    /**
     * Show a kpi in relation to organisation along its kpicomments
     *
     * Undocumented function long description
     *
     * @param string $locale AppLocale
     * @return App\Models\Kpi $kpi the kpi to be returned
     * @throws conditon
     **/
    public function organisationsKpisShow($locale, Kpi $kpi)
    {
        $org = Organisation::find(session('selected_org')['id']);
        $kpiComments = $kpi->org_kpicomments($org);
        $kpi->makeVisible(['kpicomment', 'kpicomments', 'targets', 'values', 'xaxis']);
        $kpi->kpicomments = $kpiComments;
        $kpi->kpicomment = $kpiComments->last();
        // chart data
        $t = [];
        $v = [];
        $x = [];
        foreach ($kpi->kpicomments as $comment) {
            $t[] = ['x' => Carbon::parse($comment->created_at)->format('Y-m-d'), 'y' => $comment->target, 'user' => $comment->user->name . ' [' . $comment->user->role . ']', 'comment' => $comment->comment];
            $v[] = ['x' => Carbon::parse($comment->created_at)->format('Y-m-d'), 'y' => $comment->value, 'user' => $comment->user->name . ' [' . $comment->user->role . ']', 'comment' => $comment->comment];
            $x[] = Carbon::parse($comment->created_at)->format('Y-m-d');
        }
        $kpi->targets = $t;
        $kpi->values = $v;
        $kpi->xaxis = $x;
        return $kpi;
    }

    public function organisationsPlan($locale, Action $action = null)
    {
        $org = Organisation::find(session('selected_org')['id']);
        $components = Component::all()->load('period')->makeVisible(['code_name', 'period', 'periods']);
        foreach ($components as $component) {
            $op = $component->organisationUserPeriod($org);
            $component->periods = Period::orderBy('sort_order')->get();
            foreach ($component->periods as $period) {
                if ($period->id == $op->id) {
                    $period->selected = true;
                } else {
                    $period->selected = false;
                }
            }
        }

        if ($action) {
            //TODO: check access

            if ($action->actionType->model == 'component') {
                $components = $action->components;
                $statements = $components->flatMap(function ($component) {
                    return $component->statements->sortBy('subcode', SORT_NATURAL)->makeVisible(['component', 'implementation', 'responsibility', 'period', 'subcode']);
                });
            } elseif ($action->actionType->model == 'statement') {
                $statements = $action->statements->sortBy('subcode', SORT_NATURAL)->makeVisible(['component', 'implementation', 'responsibility', 'period', 'subcode']);
            }
        } else {
            // statements periods for this organisation would be the same for their component organisationPeriod
            $statements = Statement::all()->sortBy('subcode', SORT_NATURAL)->load('component')->makeVisible(['component', 'implementation', 'responsibility', 'period', 'subcode']);
        }

        foreach ($statements as $statement) {
            // calculate component periods for this organisation
            $oup = $statement->component->organisationUserPeriod($org);
            $statement->component->makeVisible(['organisation_period']);
            $statement->component->organisation_period = $oup;
            $sp = $statement->organisationPlan($org);
            $statement->implementation = $sp->implementation;
            $statement->responsibility = $sp->responsibility;
        }

        $statements = $statements->groupBy(function ($statement) {
            return $statement->component->code;
        })->sortKeys();

        // Organisation data for report
        $organisation = $org->makeVisible(['orgcolor', 'logo']);
        // Report Chart
        $quarterchart = [];
        $quarters = Period::whereIn('id', [1, 2, 3, 4])->get();
        foreach ($quarters as $quarter) {
            $quarterchart['labels'][] = $quarter->{'name_' . $locale};
            // loop all components
            $count = 0;
            $quarterchartcomponents = [];
            foreach ($components as $component) {
                if ($component->organisationuserPeriod($organisation)->id == $quarter->id) {
                    $count += 1;
                    $quarterchartcomponents[] = $component->code_name;
                }
            }
            $quarterchart['data'][] = $count;
            $quarterchart['components'][] = $quarterchartcomponents;
        }
        $cs = Component::all()->sortBy('sort_order')->makeVisible(['code_name']);
        $quarterchart['componentsfinal'] = [];
        foreach ($cs as $c) {
            $quarterchart['componentsfinal'][] = ['codename' => $c->code_name, 'desc' => $c->{'desc_' . $locale}, 'implementation' => '[Placeholder for Implementation]?'];
        }
        // Localization
        App::setlocale($locale);
        $messages = Lang::get('messages');
        $r = ['components' => $components, 'organisation' => $organisation, 'statements' => $statements, 'quarterchart' => $quarterchart, 'messages' => $messages];
        $r = collect($r);
        return $r;
    }

    /**
     * Return a collection of statements and relations to the auditor organisation along with their guide and plan (review) type
     *
     * Undocumented function long description
     *
     * @param string $locale AppLocale
     * @return Illuminate\Support\Collection the statements
     **/
    public function organisationsPlanAuditor($locale, Action $action = null)
    {
        if ($action) {
            //TODO: check access

            if ($action->actionType->model == 'statement') {
                $statements = $action->statements->sortBy('subcode', SORT_NATURAL)->makeVisible(['concat', 'guide', 'plans', 'subcode', 'deed']);
            } else if ($action->actionType->model == 'component') {
                $components = $action->components;
                $statements = $components->flatMap(function ($component) {
                    return $component->statements->sortBy('subcode', SORT_NATURAL)->makeVisible(['concat', 'guide', 'plans', 'subcode', 'deed']);
                });
            }
        } else {
            $statements = Statement::all()->sortBy('subcode', SORT_NATURAL)->makeVisible(['concat', 'guide', 'plans', 'subcode', 'deed']);
        }

        $plans = Plan::all()->sortBy('sort_order');
        $statementPlans = [];
        $org = Organisation::find(session('selected_org')['id']);
        foreach ($statements as $statement) {
            $statementReviewPlan = $statement->reviewPlan();
            if ($statementReviewPlan) {
                $usersIds = $org->users->pluck('id');
                $r = DB::table('auditor_statement')->whereIn('user_id', $usersIds)->where('statement_id', $statement->id)->get()->first();
                $statement->guide = $r ? $r->guide : '';
            } else {
                $statement->guide = '';
            }
            foreach ($plans as $plan) {
                $selected = false;
                if ($statementReviewPlan) {
                    if ($statementReviewPlan->id == $plan->id) {
                        $selected = true;
                    }
                }
                $statementPlans[] = ['plan' => $plan, 'selected' => $selected];
            }
            $statement->plans = $statementPlans;
            $statementPlans = [];
            $statement->deed = $statement->organisationDeed($org);
            $statement->deed = $statement->deed?->load('deedHistory')->makeVisible('deedHistory');
        }
        App::setlocale($locale);
        $messages = Lang::get('messages');
        $r = ['statements' => $statements, 'messages' => $messages];
        return $r;
    }

    /**
     * Update the plan status for a statement by an auditor
     *
     * Undocumented function long description
     *
     * @param App\Http\Requests\OrganisationsPlanAuditorUpdateRequest $request load
     * @param string $locale AppLocale
     * @return \Illuminate\Http\Response
     **/
    public function organisationsPlanAuditorUpdate(OrganisationsPlanAuditorUpdateRequest $request, $locale)
    {
        // as exists?
        try {
            $org = Organisation::find(session('selected_org')['id']);
            $usersIds = $org->users->pluck('id');
            $auditor = $org->users->where('role', 'auditor')->first();
            $usersId = $auditor ? $auditor->id : auth()->user()->id;
            $data = $request->all();

            foreach ($data as $statement) {
                $guide = $statement['guide'] ?? '';
                $as = DB::table('auditor_statement')->where('statement_id', $statement['statement_id'])->whereIn('user_id', $usersIds)->get()->first();
                if ($as) {
                    DB::table('auditor_statement')->where('id', $as->id)->update(['plan_id' => $statement['plan_id'], 'user_id' => $usersId, 'guide' => $guide, 'updated_at' => Carbon::now()]);
                } else {
                    DB::table('auditor_statement')->insert(['statement_id' => $statement['statement_id'], 'plan_id' => $statement['plan_id'], 'user_id' => $usersId, 'guide' => $guide, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            abort(500, $th->getMessage());
        }
        return response('success', 200);
    }

    /**
     * Get organisation review statements
     *
     * Get organisation statements, with their deeds and return them for auditor to review them
     *
     * @param String $locale
     * @return \Illuminate\Http\Response
     **/
    public function organisationsReview($locale, Action $action = null)
    {
        function latestReview($statement, $org, $locale)
        {
            $latestReview = Review::where('statement_id', $statement->id)
                ->where('organisation_id', $org->id)
                ->latest('updated_at')
                ->first();
            if ($latestReview) {
                $reviewStatusClass = null;
                switch ($latestReview->review_status_id) {
                    case 1:
                        $reviewStatusClass = 'warning';
                        break;
                    case 2:
                        $reviewStatusClass = 'success';
                        break;
                    case 3:
                        $reviewStatusClass = 'danger';
                        break;
                    case 4:
                        $reviewStatusClass = 'info';
                        break;
                    case 5:
                        $reviewStatusClass = 'primary';
                        break;
                    default:
                        $reviewStatusClass = 'secondary';
                        break;
                }
                $latestReviewFormatted = [
                    "user" => $latestReview->user->name,
                    "review_status" => ReviewStatus::find($latestReview->review_status_id)->{'name_' . $locale}, // Assuming ReviewStatus model exists
                    'class' => $reviewStatusClass,
                    "lastUpdated" => $latestReview->updated_at->format('Y-m-d H:i:s')
                ];
                $latestReview = $latestReviewFormatted;
            } else {
                $latestReview = [
                    'user' => __('messages.notFound'),
                    "review_status" => __('messages.notFound'),
                    'class' => 'secondary',
                    "lastUpdated" => Carbon::now()->format('Y-m-d H:i:s')
                ];
            }
            return $latestReview;
        };
        $org = Organisation::find(session('selected_org')['id']);

        // unify the fetched statements of all types

        if ($action) {
            //TODO: check access
            if ($action->actionType->model == 'component') {
                $components = $action->components;
                $statements = $components->flatMap(function ($component) {
                    return $component->statements->makeVisible(['component', 'deed', 'implementation', 'guide', 'plan', 'review', 'subcode']);
                });
            } elseif ($action->actionType->model == 'statement') {
                $statements = $action->statements->makeVisible(['component', 'deed', 'implementation', 'guide', 'plan', 'review', 'subcode']);
            }
        } else {
            $statements = Statement::all()->load('component')->makeVisible(['component', 'deed', 'implementation', 'guide', 'plan', 'review', 'subcode']);
        }
        $plans = Plan::all()->sortBy('sort_order');
        // statistics
        $statistics = [
            'statements' => [
                'interview' => [
                    'statements' => [],
                    'class' => 'progress progress-bar-success',
                    'title' => Plan::where('id', 1)->first()->{'name_' . $locale},
                    'count' => 0,
                    'interviews' => [],
                ],
                'test' => [
                    'statements' => [],
                    'class' => 'progress progress-bar-success',
                    'title' => Plan::where('id', 2)->first()->{'name_' . $locale},
                    'count' => 0,
                ],
                'webform' => [
                    'statements' => [],
                    'class' => 'progress progress-bar-success',
                    'title' => Plan::where('id', 3)->first()->{'name_' . $locale},
                    'count' => 0,
                    'webforms' => [],
                    'components' => [],
                ],
                'check' => [
                    'statements' => [],
                    'class' => 'progress progress-bar-success',
                    'title' => Plan::where('id', 5)->first()->{'name_' . $locale},
                    'count' => 0,
                ],
            ],
            'unplanned' => [
                'statements' => [],
                'count' => 0
            ],
            'users' => $org->usersOnly(),
        ];
        foreach ($statements as $statement) {
            $statement->content_en = $statement->subcode . '-' . $statement->content_en;
            $statement->content_se = $statement->subcode . '-' . $statement->content_se;
            $op = $statement->component->organisationPeriod($org);
            $statement->component->makeVisible(['organisation_period']);
            $statement->component->organisation_period = $op;
            //$statement->plan = null;
            $statement->implementation = null;
            $op = $statement->organisationPlan($org);
            if ($op) {
                //$statement->plan = $op->plan;
                $statement->implementation = $op->implementation;
            }
            $statement->deed = $statement->organisationDeed($org);
            $statement->review = $statement->organisationReview($org);
            $statementReviewPlan = $statement->reviewPlan();
            if ($statementReviewPlan) {
                $usersIds = $org->users->pluck('id');
                $r = DB::table('auditor_statement')->whereIn('user_id', $usersIds)->where('statement_id', $statement->id)->get()->first();
                $statement->guide = $r?->guide;
            } else {
                $statement->guide = '';
            }
            $statement->plan = ['name_en' => '', 'name_se' => ''];
            foreach ($plans as $plan) {
                if ($statementReviewPlan) {
                    if ($statementReviewPlan->id == $plan->id) {
                        $statement->plan = $plan;
                    }
                }
            }
            if (isset($statement->plan['id'])) {
                switch ($statement->plan->id) {
                        // case interview
                    case 1:
                        $statement->latestReview = latestReview($statement, $org, $locale);
                        $statement->makeVisible(['latestReview']);
                        $statistics['statements']['interview']['statements'][] = $statement;
                        // inject statement latest review status for pill

                        $statistics['statements']['interview']['count'] += 1;
                        break;
                    case 2:
                        $statistics['statements']['test']['statements'][] = $statement;
                        $statistics['statements']['test']['count'] += 1;
                        break;
                        // case webform    
                    case 3:
                        $statement->latestReview = latestReview($statement, $org, $locale);
                        $statement->makeVisible(['latestReview']);
                        $statistics['statements']['webform']['statements'][] = $statement;
                        $statistics['statements']['webform']['count'] += 1;
                        break;
                    case 4:
                        break;
                    case 5:
                        $statistics['statements']['check']['statements'][] = $statement;
                        $statistics['statements']['check']['count'] += 1;
                        break;
                    default:
                        $statistics['unplanned']['statements'][] = $statement;
                        $statistics['unplanned']['count'] += 1;
                        break;
                }
            } else {
                $statistics['unplanned']['statements'][] = $statement;
                $statistics['unplanned']['count'] += 1;
            }
        };
        // interviews
        // find all interviews by the organisation
        $orgInterviews = Interview::where('organisation_id', $org->id)->with('statements')->get();

        //$statistics['statements']['interview']['interviews'] = $orgInterviews;
        // available interviews
        $available = $statistics['statements']['interview']['statements'];
        // available webforms
        $availableWebforms = $statistics['statements']['webform']['statements'];
        // components
        // webform components
        $webformComponents = [];
        foreach ($orgInterviews as $orgInterview) {
            // Separate interviews based on plan id
            switch ($orgInterview->plan_id) {
                    // interview
                case 1:
                    $statistics['statements']['interview']['interviews'][] = $orgInterview;
                    break;
                    // webform    
                case 3:
                    // inject interviewee for webform
                    $orgInterview->interviewee = User::where('id', $orgInterview->interviewee)->first();
                    $statistics['statements']['webform']['webforms'][] = $orgInterview;
                    break;
            }
            // inject creator
            $orgInterview->creator = User::where('id', $orgInterview->creator_id)->first();
            // inject latest deed (for value review on conduct)
            $orgInterview->statements->transform(function ($statement) use ($orgInterview, $locale, $statistics, $org) {
                // Retrieve the latest deed associated with the statement
                $latestDeed = Deed::where('statement_id', $statement->id)
                    ->latest('updated_at')
                    ->first();
                // Add the latest deed as an attribute to the statement
                // if deed
                $statement->makeVisible(['latestDeed']);
                if ($latestDeed) {
                    $latestDeedFormatted = [
                        "value" => $latestDeed->value,
                        "comment" => $latestDeed->comment,
                        "user" => User::find($latestDeed->user_id)->name,
                        "lastUpdated" => $latestDeed->updated_at->format('Y-m-d H:i:s'),
                        "id" => $latestDeed->id,
                    ];
                    $latestDeed = $latestDeedFormatted;
                } else {
                    $latestDeed = [
                        "value" => 5,
                        "comment" => "No deed found",
                        "user" => "None",
                        "lastUpdated" => Carbon::now()->format('Y-m-d H:i:s'),
                        "id" => null,
                    ];
                }
                $statement->latestDeed = $latestDeed;
                // Retrieve the latest review associated with the statement and the same organization ID as the user
                /*
                $latestReview = Review::where('statement_id', $statement->id)
                    ->where('organisation_id', User::find($orgInterview->creator_id)->organisation->id)
                    ->latest('updated_at')
                    ->first();
                    */
                $latestReview = Review::where('statement_id', $statement->id)
                    ->where('organisation_id', $org->id)
                    ->latest('updated_at')
                    ->first();
                if ($latestReview) {
                    $reviewStatusClass = null;
                    switch ($latestReview->review_status_id) {
                        case 1:
                            $reviewStatusClass = 'warning';
                            break;
                        case 2:
                            $reviewStatusClass = 'success';
                            break;
                        case 3:
                            $reviewStatusClass = 'danger';
                            break;
                        case 4:
                            $reviewStatusClass = 'info';
                            break;
                        case 5:
                            $reviewStatusClass = 'primary';
                            break;
                        default:
                            $reviewStatusClass = 'secondary';
                            break;
                    }
                    $latestReviewFormatted = [
                        "user" => $latestReview->user->name,
                        "review_status" => ReviewStatus::find($latestReview->review_status_id)->{'name_' . $locale}, // Assuming ReviewStatus model exists
                        'class' => $reviewStatusClass,
                        "lastUpdated" => $latestReview->updated_at->format('Y-m-d H:i:s'),
                        "review" => $latestReview->review,
                    ];
                    $latestReview = $latestReviewFormatted;
                } else {
                    $latestReview = [
                        'user' => __('messages.notFound'),
                        "review_status" => __('messages.notFound'),
                        'class' => 'secondary',
                        "lastUpdated" => Carbon::now()->format('Y-m-d H:i:s'),
                        "review" => '',
                    ];
                }
                $statement->latestReview = $latestReview;
                // Make latestDeed and latestReview visible
                $statement->makeVisible(['latestDeed', 'latestReview']);
                return $statement;
            });
            // webform component statements
            foreach ($orgInterview->statements as $st) {
                // component grouping for webform
                if ($orgInterview->plan_id == 3) {
                    // find comp
                    $orgComponent = $st->component;
                    $c = ['id' => $orgComponent->id, 'text' => $orgComponent->code . '-' . $orgComponent['name_' . $locale], 'st' => [$st]];
                    // does it exist?
                    $indx = -1;
                    foreach ($webformComponents as $ind => $wfc) {
                        if ($wfc['id'] == $orgComponent->id) {
                            $indx = $ind;
                            break;
                        }
                    }
                    if ($indx == -1) {
                        // does not exist, write new
                        $webformComponents[] = $c;
                    } else {
                        // else
                        $webformComponents[$indx]['st'][] = $st;
                    }
                }
                $st->content_en = $st->subcode . '-' . $st->content_en;
                $st->content_se = $st->subcode . '-' . $st->content_se;
                // clear interviews
                foreach ($available as $inde => $stx) {
                    if ($stx['id'] == $st['id']) {
                        unset($available[$inde]);
                    }
                }
                // clear webforms
                foreach ($availableWebforms as $key => $webform) {
                    if ($webform['id'] == $st['id']) {
                        unset($availableWebforms[$key]);
                    }
                }
            }
        }
        // interviews rebuild
        $x = [];
        foreach ($available as $av) {
            $x[] = $av;
        }
        $statistics['statements']['interview']['statements'] = $x;
        // webform rebuild
        $w = [];
        foreach ($availableWebforms as $avw) {
            $w[] = $avw;
        }
        // components
        // sort by id
        $webformComponents = collect($webformComponents);
        $statistics['statements']['webform']['components'] = $webformComponents;
        $statistics['statements']['webform']['statements'] = $w;
        App::setlocale($locale);
        $messages = Lang::get('messages');
        $r = ['statements' => $statements, 'messages' => $messages, 'statistics' => $statistics];
        $r = collect($r);
        return $r;
    }
    /**
     * Get all the info required for the organisation review modal
     *
     * This includes all the statements under this actionable which have a plan of interview, along with the users of this organization
     *
     * @params app locale, the requesting organisation and the action for this review
     * @return Collection interviewable statements, organisations users
     **/
    public function organisationsReviewInterview($locale, Organisation $organisation, Action $action)
    {
        // Get all actionables for the provided action_id
        $actionables = DB::table('actionables')
            ->where('action_id', $action->id)
            ->get();
        // Extract the statement IDs and component IDs from the actionables
        $statementIds = $actionables->where('actionable_type', 'App\\Models\\Statement')->pluck('actionable_id');
        $componentIds = $actionables->where('actionable_type', 'App\\Models\\Component')->pluck('actionable_id');
        // Retrieve the Statement models based on the extracted statement IDs
        $statements = Statement::whereIn('id', $statementIds)->get();
        // Retrieve additional statements associated with the components
        $additionalStatements = Statement::whereIn('component_id', $componentIds)->get();
        // Merge the directly associated statements with the additional statements
        $allStatements = $statements->merge($additionalStatements);
        // Get the statement IDs from the allStatements collection
        $statementIds = $allStatements->pluck('id');
        // Retrieve the statements that can be found in the auditor_statement table with plan_id of 1
        $interviewStatements = Statement::whereIn('id', $statementIds)
            ->whereIn('id', function ($query) use ($organisation) {
                $query->select('statement_id')
                    ->from('auditor_statement')
                    ->where('plan_id', 1);
            })
            ->get();
        // Retrieve only the users from the organization who have the role "user"
        $users = $organisation->users()->where('role', 'user')->get();
        // Get the organisation ID of the authenticated user
        $authUserOrgId = Auth::user()->organisation_id;

        // Fetch reviews for the interview statements, only for the organization of the authenticated user
        $reviews = Review::whereIn('statement_id', $interviewStatements->pluck('id'))
            ->where('organisation_id', $authUserOrgId)
            ->get();
        // Map the interview statements to include review status and review status ID   
        $interviewStatements->transform(function ($statement) use ($reviews, $locale) {
            $review = $reviews->where('statement_id', $statement->id)->first();
            if ($review) {
                $reviewStatus = ReviewStatus::find($review->review_status_id);
                $reviewStatusName = $reviewStatus ? $reviewStatus->{'name_' . $locale} : null;
                $reviewStatusId = $review->review_status_id;
            } else {
                $reviewStatusName = __('messages.notReviewed');
                $reviewStatusId = 0;
            }
            // Set class based on review_status_id
            switch ($reviewStatusId) {
                case 1:
                    $class = 'warning';
                    break;
                case 2:
                    $class = 'success';
                    break;
                case 3:
                    $class = 'danger';
                    break;
                case 4:
                    $class = 'info';
                    break;
                case 5:
                    $class = 'primary';
                    break;
                default:
                    $class = 'secondary';
                    break;
            }
            return $statement->setAttribute('reviewStatus', $reviewStatusName)
                ->setAttribute('reviewStatusId', $reviewStatusId)
                ->setAttribute('class', $class);
        });
        // Dynamically make the additional attributes visible for this specific collection
        $interviewStatements->makeVisible(['reviewStatus', 'reviewStatusId', 'class']);
        // Return the collection of statements with plan_id of 1 and the organization users with the role "user"
        $interviews = Interview::with('statements')->join('users', 'interviews.user_id', '=', 'users.id')
            ->where('users.organisation_id', $authUserOrgId)
            ->select('interviews.*')
            ->orderByDesc('interviews.id')
            ->get();
        foreach ($interviews as $interview) {
            $interview->creator = User::find($interview->creator_id)->name;
            $interview->interviewee = User::find($interview->user_id)->name;
            // inject deed
            $interview->statements->transform(function ($statement) use ($interview, $locale) {
                // Retrieve the latest deed associated with the statement
                $latestDeed = Deed::where('statement_id', $statement->id)
                    ->latest('updated_at')
                    ->first();
                // Add the latest deed as an attribute to the statement
                // if deed
                $statement->makeVisible(['latestDeed']);
                if ($latestDeed) {
                    $latestDeedFormatted = [
                        "value" => $latestDeed->value,
                        "comment" => $latestDeed->comment,
                        "user" => User::find($latestDeed->user_id)->name,
                        "lastUpdated" => $latestDeed->updated_at->format('Y-m-d H:i:s'),
                        "id" => $latestDeed->id,
                    ];
                    $latestDeed = $latestDeedFormatted;
                } else {
                    $latestDeed = [
                        "value" => 5,
                        "comment" => "No deed found",
                        "user" => "None",
                        "lastUpdated" => Carbon::now()->format('Y-m-d H:i:s'),
                        "id" => null,
                    ];
                }
                $statement->latestDeed = $latestDeed;

                // Retrieve the latest review associated with the statement and the same organization ID as the user
                $latestReview = Review::where('statement_id', $statement->id)
                    ->where('organisation_id', User::find($interview->creator_id)->organisation->id)
                    ->latest('updated_at')
                    ->first();
                if ($latestReview) {
                    $reviewStatusClass = null;
                    switch ($latestReview->review_status_id) {
                        case 1:
                            $reviewStatusClass = 'warning';
                            break;
                        case 2:
                            $reviewStatusClass = 'success';
                            break;
                        case 3:
                            $reviewStatusClass = 'danger';
                            break;
                        case 4:
                            $reviewStatusClass = 'info';
                            break;
                        case 5:
                            $reviewStatusClass = 'primary';
                            break;
                        default:
                            $reviewStatusClass = 'secondary';
                            break;
                    }
                    $latestReviewFormatted = [
                        "user" => $latestReview->user->name,
                        "review_status" => ReviewStatus::find($latestReview->review_status_id)->{'name_' . $locale}, // Assuming ReviewStatus model exists
                        'class' => $reviewStatusClass,
                        "lastUpdated" => $latestReview->updated_at->format('Y-m-d H:i:s')
                    ];
                    $latestReview = $latestReviewFormatted;
                } else {
                    $latestReview = [
                        'user' => __('messages.notFound'),
                        "review_status" => __('messages.notFound'),
                        'class' => 'secondary',
                        "lastUpdated" => Carbon::now()->format('Y-m-d H:i:s')
                    ];
                }
                $statement->latestReview = $latestReview;

                // Make latestDeed and latestReview visible
                $statement->makeVisible(['latestDeed', 'latestReview']);

                return $statement;
            });
        }
        return [
            'interviewStatements' => $interviewStatements,
            'users' => $users,
            'interviews' => $interviews,
        ];
    }
    /**
     * Return a collection of all organisation risks along the messages dictionaries
     *
     * no further desc is neccessary
     *
     * @param String $locale AppLocale
     * @return Illuminate\Database\Eloquent\Collection
     **/
    public function organisationsRisksIndex($locale)
    {
        $org = Organisation::find(session('selected_org')['id']);
        $risks = $org->risks->load(['organisation', 'risk_comments', 'component', 'user'])->makeVisible(['created_at_for_humans', 'factor', 'organisation', 'risk', 'risk_comments', 'component', 'user']);
        foreach ($risks as $risk) {
            $risk->risk = $risk->risk();
            $risk->factor = $risk->factor();
            if ($risk->component) {
                $risk->component->makeVisible(['code_name']);
            }
            foreach ($risk->risk_comments as $risk_comment) {
                $risk_comment->load('user')->makeVisible(['created_at_for_humans', 'user']);
            }
            $risk->makeVisible(['risk_comments_sorted']);
            $risk->risk_comments_sorted = collect($risk->risk_comments)->sortByDesc('id')->values()->all();
        }
        App::setlocale($locale);
        $messages = Lang::get('messages');
        $now = Carbon::now()->format('Y-m-d H:i:s T');
        $colours = ['success' => '#28c76f', 'lowMed' => '#cab707', 'warning' => '#FF9F43', 'medHigh' => '#ff5f43', 'danger' => '#EA5455'];
        $series = [count($risks->filter(function ($item) {
            return $item->risk['class'] == 'success';
        })->all()), count($risks->filter(function ($item) {
            return $item->risk['class'] == 'low-med';
        })->all()), count($risks->filter(function ($item) {
            return $item->risk['class'] == 'warning';
        })->all()), count($risks->filter(function ($item) {
            return $item->risk['class'] == 'med-high';
        })->all()), count($risks->filter(function ($item) {
            return $item->risk['class'] == 'danger';
        })->all())];
        // Scatter Data New with Range
        /*
        $dataSets = [];
        foreach ($risks as $risk) {
            $dataSets[] = ['label' => $risk->title, 'backgroundColor' => $risk->risk['colour'], 'borderColor' => $risk->risk['colour']];
        };
        */
        // Scatter Data New with Range End
        //Scatter Data
        //Carbon::create()->startOfMonth()->month($period->start)->locale(__('messages.localeCarbon'))->getTranslatedMonthName('M');
        $dataSets = [];
        $rangeDates = [$messages['rangeAllTime']];
        $risks = $risks->sortBy('created_at');

        $risks->each(function ($risk) use (&$rangeDates) {
            if (!(in_array(Carbon::parse($risk->created_at)->locale(__('messages.localeCarbon'))->isoFormat('Y MMMM'), $rangeDates))) {
                $rangeDates[] = Carbon::parse($risk->created_at)->locale(__('messages.localeCarbon'))->isoFormat('Y MMMM');
            }
        });

        foreach ($rangeDates as $key => $date) {
            if ($key === 0) {
                $risksUpToMonth = $risks;
            } else {
                $risksUpToMonth = $risks->where('created_at', '<=', Carbon::parse($date)->endOfMonth());
            }

            foreach ($risksUpToMonth as $scatterRisk) {
                $dataSets[] = [
                    'label' => $scatterRisk->title,
                    'backgroundColor' => $scatterRisk->risk['colour'],
                    'borderColor' => $scatterRisk->risk['colour'],
                    'data' => [[
                        'x' => $scatterRisk->consequence,
                        'y' => $scatterRisk->probability,
                        'r' => 10 * count($risks->filter(function ($item) use ($scatterRisk) {
                            return ($item->consequence == $scatterRisk->consequence && $item->probability == $scatterRisk->probability);
                        })->all())
                    ]],
                    'count' => count($risks->filter(function ($item) use ($scatterRisk) {
                        return ($item->consequence == $scatterRisk->consequence && $item->probability == $scatterRisk->probability);
                    })->all()), 'date' => $date
                ];
            }
        };

        //Scatter Date End
        // history data new
        // x axis
        $historyCategories = [];
        // y axis
        $historyLow = [];
        $historyLowMed = [];
        $historyMed = [];
        $historyMedHigh = [];
        $historyHigh = [];
        for ($i = 0; $i < 13; $i++) {
            $month = Carbon::now()->addMonths($i - 12);
            $monthlyRisks = $risks->filter(function ($item) use ($month) {
                return Carbon::parse($item->created_at) <= $month->endOfMonth();
            });
            $historyLow[] = count($monthlyRisks->filter(function ($item) {
                return $item->risk['class'] == 'success';
            })->all());
            $historyLowMed[] = count($monthlyRisks->filter(function ($item) {
                return $item->risk['class'] == 'low-med';
            })->all());
            $historyMed[] = count($monthlyRisks->filter(function ($item) {
                return $item->risk['class'] == 'warning';
            })->all());
            $historyMedHigh[] = count($monthlyRisks->filter(function ($item) {
                return $item->risk['class'] == 'med-high';
            })->all());
            $historyHigh[] = count($monthlyRisks->filter(function ($item) {
                return $item->risk['class'] == 'danger';
            })->all());
            $year = $month->format('y');
            $day = $month->locale(__('messages.localeCarbon'))->getTranslatedShortMonthName('M');
            $historyCategories[] = $day . '-' . $year;
        }
        $history = ['xaxis' => $historyCategories, 'yaxis' => ['historyLow' => $historyLow, 'historyLowMed' => $historyLowMed, 'historyMed' => $historyMed, 'historyMedHigh' => $historyMedHigh, 'historyHigh' => $historyHigh]];
        // history data new end
        // history data old
        /*
        $cats = [];
        $avgs = [];
        for ($i = 0; $i < 13; $i++) {
            $r = Carbon::now()->addMonths($i - 12);
            $intersectedRisks = $risks->filter(function ($item) use ($r) {
                return ($r->startOfMonth() <= Carbon::parse($item->created_at) && Carbon::parse($item->created_at) <= $r->endOfMonth());
            });
            $a = 0;
            $a += round(floatval($intersectedRisks->average('factor')), 2);
            $y = $r->format('y');
            $d = $r->locale(__('messages.localeCarbon'))->getTranslatedShortMonthName('M');
            $cat = $d . '-' . $y;
            $cats[] = $cat;
            $avgs[] = $a;
        };
        */
        // history data old end
        $r = ['messages' => $messages, 'risks' => $risks, 'now' => $now, 'colours' => $colours, 'series' => $series, 'dataSets' => $dataSets, 'history' => $history, 'rangeDates' => $rangeDates];
        $r = collect($r);
        return $r;
    }

    /**
     * Update or create a statement action
     *
     * Update or create a statement action in relation to an organisation, e.g: set statement x action for organization y to be "value 2, comment something"
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     **/
    public function organisationsStatementsDeedsUpdate(OrganisationsStatementsDeedsUpdateRequest $request)
    {
        $data = $request->validated();
        DB::transaction(function () use ($data) {
            $o = Organisation::find(session('selected_org')['id']);
            // find if this statement already has an organisation deed
            $d = Deed::where('organisation_id', $o->id)->where('statement_id', $data['statement_id'])->first();
            if ($d) {
                if ($d['value'] !== $data['value']) {
                    DeedHistory::create([
                        'deed_id' => $d['id'],
                        'value' => $data['value'],
                        'user_id' => auth()->user()->id,
                    ]);
                }
                $d->update(['user_id' => Auth::user()->id, 'value' => $data['value'], 'comment' => $data['comment']]);
            } else {
                $d = Deed::create(['organisation_id' => $o->id, 'statement_id' => $data['statement_id'], 'user_id' => Auth::user()->id, 'value' => $data['value'], 'comment' => $data['comment']]);
                DeedHistory::create([
                    'deed_id' => $d['id'],
                    'value' => $data['value'],
                    'user_id' => auth()->user()->id,
                ]);
            }

            $review = Review::where('organisation_id', $o->id)
                ->where('statement_id', $data['statement_id'])
                ->where(function ($query) {
                    $query->whereRelation('reviewStatus', 'name_en', 'Accepted')
                        ->orWhereRelation('reviewStatus', 'name_en', 'Rejected');
                })
                ->first();

            if ($review) {
                $reviewStatus = ReviewStatus::where('name_en', 'Pending')->first();
                $review->reviewStatus()->associate($reviewStatus);
                $review->save();
            }
        });
        return response('success', 200);
    }

    /**
     * Update or create an array of statement deeds
     *
     * Undocumented function long description
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @throws \Throwable
     **/
    public function organisationsStatementsDeedsUpdateAll(OrganisationsStatementsDeedsUpdateAllRequest $request)
    {
        $data = $request->validated();
        $organisation = Organisation::find(session('selected_org')['id']);
        try {
            DB::transaction(function () use ($data, $organisation) {
                foreach ($data['statements'] as $statement) {
                    // exists?
                    $deed = Deed::where('organisation_id', $organisation->id)->where('statement_id', $statement['id'])->first();
                    if ($deed) {
                        // update
                        $deed->update(['user_id' => Auth::user()->id, 'value' => $statement['value'], 'comment' => $statement['comment']]);
                    } else {
                        Deed::create(['organisation_id' => $organisation->id, 'statement_id' => $statement['id'], 'user_id' => Auth::user()->id, 'value' => $statement['value'], 'comment' => $statement['comment']]);
                    }

                    $review = Review::where('organisation_id', $organisation->id)
                        ->where('statement_id', $statement['id'])
                        ->where(function ($query) {
                            $query->whereRelation('reviewStatus', 'name_en', 'Accepted')
                                ->orWhereRelation('reviewStatus', 'name_en', 'Rejected');
                        })
                        ->first();

                    if ($review) {
                        $reviewStatus = ReviewStatus::where('name_en', 'Pending')->first();
                        $review->reviewStatus()->associate($reviewStatus);
                        $review->save();
                    }
                }
            });
            return response('success', 200);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Update the plan of the statement in relation to an organisation, e.g: set statement x plan for organisation x to be "Inspection"
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function organisationsStatementsPlansUpdate(OrganisationsStatementsPlansUpdateRequest $request)
    {
        //
        $data = $request->validated();
        $o = Organisation::find(session('selected_org')['id']);;
        // find if this statement already has an entry
        $x = DB::table('organisation_statement')->where('organisation_id', $o->id)->where('statement_id', $data['statement_id'])->first();
        $responsibility = null;
        if (isset($data['responsibility'])) {
            $responsibility = $data['responsibility'];
        }
        if ($x) {
            DB::table('organisation_statement')->where('organisation_id', $o->id)->where('statement_id', $data['statement_id'])->update(['implementation' => $data['implementation'], 'responsibility' => $responsibility, 'updated_at' => Carbon::now()]);
        } else {
            $o->statements()->attach([$data['statement_id'] => ['implementation' => $data['implementation'], 'responsibility' => $responsibility, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]]);
        }
        return response('success', 200);
    }

    /**
     * Update the review of the statement in relation to an organisation
     *
     * e.g: set statement x review or organisation y and deed z to be "you did this wrong"
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     **/
    public function organisationsStatementsReviewsUpdate(OrganisationsStatementsReviewsUpdateRequest $request)
    {
        $data = $request->validated();
        $o = Organisation::find(session('selected_org')['id']);
        // find if this statement has a a review
        $r = Review::where('organisation_id', $o->id)->where('statement_id', $data['statement_id'])->first();
        if ($r) {
            // update
            $r->update(['user_id' => Auth::user()->id, 'review_status_id' => $data['review_status_id'], 'review' => $data['review']]);
        } else {
            // create
            Review::create(['organisation_id' => $o->id, 'statement_id' => $data['statement_id'], 'user_id' => Auth::user()->id, 'review_status_id' => $data['review_status_id'], 'review' => $data['review']]);
        }
        return response('success', 200);
    }

    /**
     * Update organisation details
     *
     * Undocumented function long description
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     **/
    public function organisationsUpdate(AxiosOrganisationUpdateRequest $request)
    {
        $data = $request->validated();
        $organisation = Organisation::find(session('selected_org')['id']);
        $update = [];
        // logo uploaded?
        if (isset($data['logo'])) {
            $update['logofile'] = Storage::putFile('public/organisations/logos', $data['logo']);
        }
        // color uploaded?
        if (isset($data['color'])) {
            $update['color'] = $data['color'];
        }
        // has phone?
        if (isset($data['phone'])) {
            $update['phone'] = $data['phone'];
        }
        if (isset($data['address1'])) {
            $update['address1'] = $data['address1'];
        }
        if (isset($data['address2'])) {
            $update['address2'] = $data['address2'];
        }
        if (isset($data['email'])) {
            $update['email'] = $data['email'];
        }
        if (isset($data['website'])) {
            $update['website'] = $data['website'];
        }
        $organisation->update($update);
        return $organisation->makeVisible(['orgcolor', 'logo']);
    }

    /**
     * Update the review from the conduct interview
     *
     * Undocumented function long description
     *
     * @param $locale App Locale
     * @param Organisation the organisation
     * @param Request the update reques
     * @return Response
     **/
    public function reviewConductUpdate($locale, Organisation $organisation, Request $request)
    {
        try {
            $data = $request->all();
            if ($data['review'] == '' || !(isset($data['review'])) || $data['review'] == null) {
                return response('Review field is required', 500);
            }
            $data['user_id'] = Auth::user()->id;
            $data['organisation_id'] = $organisation->id;
            // find the latest review of this statement done by this user
            $review = Review::where('statement_id', $data['statement_id'])->where('user_id', $data['user_id'])->where('organisation_id', $data['organisation_id'])->first();
            if ($review) {
                $review->update([
                    'review_status_id' => $data['review_status_id'],
                    'review' => $data['review']
                ]);
                $review->save();
            } else {
                Review::create($data);
            }
            // update deed
            if ($data['deed_id']) {
                $deed = Deed::where('id', $data['deed_id'])->first();
                if ($deed) {
                    $deed->update([
                        'value' => $data['value'],
                    ]);
                }
            }
            return response('success', 200);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Store a risk comment
     *
     * N/A
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     **/
    public function riskCommentsStore(RiskCommentStoreRequest $request)
    {
        $data = $request->validated();
        $risk = Risk::where('id', $data['risk_id'])->first();
        // verify this risk belongs to the user organisation
        if ($risk->organisation->id == Auth::user()->organisation->id) {
            $data['user_id'] = Auth::user()->id;
            RiskComment::create($data);
            return response('success', 200);
        } else {
            abort(403, 'This risk does not belong to your organisation, you can not comment on it.');
        }
    }


    /**
     * Return a risk along with messages dictionaries
     *
     * no further desc is required
     *
     * @param string $locale App locale
     * @param App\Models\Risk $risk the risk to be returned
     * @return Illuminate\Database\Eloquent\Collection
     **@throws Illuminate\Http\Response when the user is not authorized ot access said risk
     */
    public function risksShow($locale, Risk $risk)
    {
        // abort 403 if the risk requested does not belong to the user organisation
        if ($risk->organisation_id != Auth::user()->organisation->id) {
            return response('This risk does not belong to your organisation!', 403);
        } else {
            App::setlocale($locale);
            $messages = Lang::get('messages');
            $risk->load('risk_comments')->makeVisible('risk_comments', 'risk_comments_sorted');
            foreach ($risk->risk_comments as $risk_comment) {
                $risk_comment->load('user')->makeVisible(['created_at_for_humans', 'user']);
            }
            $risk->risk_comments_sorted = collect($risk->risk_comments)->sortByDesc('id')->values()->all();
            $r = ['messages' => $messages, 'risk' => $risk];
            $r = collect($r);
            return $r;
        }
    }

    /**
     * Return a sanction
     *
     * Undocumented function long description
     *
     * @param string $locale app locale
     * @return Illuminate\Database\Eloquent\Collection
     **/
    public function sanction($locale, Sanction $sanction)
    {
        $sanction = $sanction->makeVisible(['desc_en', 'desc_se']);
        App::setlocale($locale);
        $messages = Lang::get('messages');
        $r = ['sanction' => $sanction, 'messages' => $messages];
        $r = collect($r);
        return $r;
    }

    public function sanctionFileDelete($locale, Sanction $sanction, SanctionFile $sanctionFile)
    {
        Storage::delete($sanctionFile['path']);
        $sanctionFile->delete();

        return ['success' => true];
    }

    public function sanctionFileUpload(SanctionFileUploadRequest $request, $locale, Sanction $sanction)
    {
        $uuid = Str::uuid();
        $ext = $request->file('file')->extension();
        $path = $request->file('file')->storeAs('public/sanctions/uploads', "$uuid.$ext");

        SanctionFile::create([
            'title' => $request->post('title'),
            'path' => $path,
            'sanction_id' => $sanction->id
        ]);

        return ['success' => true];
    }

    public function sanctionFiles($locale, Sanction $sanction)
    {
        $files = SanctionFile::where('sanction_id', $sanction->id)->get();

        return $files->map(function ($file) {
            $file->size = number_format(Storage::size($file->path) / 1024);
            return $file;
        });
    }

    /**
     * Return all sanctions along with dictionary
     *
     * Undocumented function long description
     *
     * @param string $locale app locale
     * @return Illuminate\Database\Eloquent\Collection
     **/
    public function sanctions($locale, Request $request)
    {
        $start = $request->get('start');
        $length = $request->get('length');
        $searchVal = $request->get('search')['value'];
        $orderByColIndex = $request->get('order')[0]['column'];
        $orderByColName = $orderBy ?? $request->get('columns')[$orderByColIndex]['data'];
        $orderByColName = str_replace('_for_humans', '', $orderByColName);
        $orderDir = $request->get('order')[0]['dir'];
        $filterByDpa = $request->get('filters')['dpa_id'];
        $filterBySni = $request->get('filters')['sni_id'];
        $filterByStatement = $request->get('filters')['statement_id'];
        $filterByType = $request->get('filters')['type_id'];
        $filterByUser = $request->get('filters')['user_id'];

        $sanctions = Sanction::select('sanctions.*')
            ->when($searchVal, function ($query, $searchVal) {
                $query->where(function ($query) use ($searchVal) {
                    $query->where('sanctions.id', 'like', "%$searchVal%")
                        ->orWhereDate('sanctions.created_at', 'like', "%$searchVal%")
                        ->orWhereRelation('dpa', 'title', 'like', "Category:%$searchVal%")
                        ->orWhereDate('sanctions.decided_at', 'like', "%$searchVal%")
                        ->orWhere('sanctions.fine', 'like', "%$searchVal%")
                        ->orWhere('sanctions.title', 'like', "%$searchVal%")
                        ->orWhereRaw('LOWER(sanctions.desc_en) LIKE ?', "{\"ops\":[{\"insert\":\"%" . strtolower($searchVal) . "%")
                        ->orWhereRaw('LOWER(sanctions.desc_se) LIKE ?', "{\"ops\":[{\"insert\":\"%" . strtolower($searchVal) . "%");
                });
            })->when($filterByDpa, function ($query, $filterByDpa) {
                $query->where('dpa_id', $filterByDpa);
            })->when($filterBySni, function ($query, $filterBySni) {
                if ($filterBySni == -1) {
                    $query->whereNull('sni_id');
                } else {
                    $query->where('sni_id', $filterBySni);
                }
            })->when($filterByStatement, function ($query, $filterByStatement) {
                if ($filterByStatement == -1) {
                    $query->whereDoesntHave('statements');
                } else {
                    $query->join('sanction_statement', function ($join) use ($filterByStatement) {
                        $join->on('sanctions.id', '=', 'sanction_statement.sanction_id')
                            ->where('sanction_statement.statement_id', $filterByStatement);
                    });
                }
            })->when($filterByType, function ($query, $filterByType) {
                if ($filterByType == -1) {
                    $query->whereNull('type_id');
                } else {
                    $query->where('type_id', $filterByType);
                }
            })->when($filterByUser, function ($query, $filterByUser) {
                $query->where('user_id', $filterByUser);
            })->when($orderByColName, function ($query, $orderByColName) use ($orderDir) {
                if ($orderByColName == 'dpa') {
                    $query->join('dpas', 'sanctions.dpa_id', '=', 'dpas.id')
                        ->orderBy('dpas.title', $orderDir);
                } else {
                    $query->orderBy($orderByColName, $orderDir);
                }
            })->skip($start)
            ->take($length)
            ->get();

        $sanctionsTotal = Sanction::count();
        $sanctionsFiltered = Sanction::when($searchVal, function ($query, $searchVal) {
            $query->where(function ($query) use ($searchVal) {
                $query->where('sanctions.id', 'like', "%$searchVal%")
                    ->orWhereDate('sanctions.created_at', 'like', "%$searchVal%")
                    ->orWhereRelation('dpa', 'title', 'like', "Category:%$searchVal%")
                    ->orWhereDate('sanctions.decided_at', 'like', "%$searchVal%")
                    ->orWhere('sanctions.fine', 'like', "%$searchVal%")
                    ->orWhere('sanctions.title', 'like', "%$searchVal%")
                    ->orWhereRaw('LOWER(sanctions.desc_en) LIKE ?', "{\"ops\":[{\"insert\":\"%" . strtolower($searchVal) . "%")
                    ->orWhereRaw('LOWER(sanctions.desc_se) LIKE ?', "{\"ops\":[{\"insert\":\"%" . strtolower($searchVal) . "%");
            });
        })->when($filterByDpa, function ($query, $filterByDpa) {
            $query->where('dpa_id', $filterByDpa);
        })->when($filterBySni, function ($query, $filterBySni) {
            if ($filterBySni == -1) {
                $query->whereNull('sni_id');
            } else {
                $query->where('sni_id', $filterBySni);
            }
        })->when($filterByStatement, function ($query, $filterByStatement) {
            if ($filterByStatement == -1) {
                $query->whereDoesntHave('statements');
            } else {
                $query->join('sanction_statement', function ($join) use ($filterByStatement) {
                    $join->on('sanctions.id', '=', 'sanction_statement.sanction_id')
                        ->where('sanction_statement.statement_id', $filterByStatement);
                });
            }
        })->when($filterByType, function ($query, $filterByType) {
            if ($filterByType == -1) {
                $query->whereNull('type_id');
            } else {
                $query->where('type_id', $filterByType);
            }
        })->when($filterByUser, function ($query, $filterByUser) {
            $query->where('user_id', $filterByUser);
        })->count();

        $sanctions->load(['articles', 'dpa', 'user', 'sni', 'type', 'statements', 'tags', 'sanctionFiles'])->makeVisible(['articles', 'articlesSorted', 'created_at_for_humans', 'started_at_for_humans', 'decided_at_for_humans', 'published_at_for_humans', 'dpa', 'url', 'etid', 'updated_at_for_humans', 'user', 'party', 'sni', 'type', 'source', 'statements', 'tags', 'sanctionFiles']);

        foreach ($sanctions as $sanction) {
            $articles = $sanction->articles;
            $sanction->articlesSorted = $articles->sortBy('title')->values();
            $sanction->dpa->load('country')->makeVisible(['country', 'name']);

            if ($sanction->currency?->symbol && $sanction->currency->symbol != 'EUR') {
                $currency = Currency::where('symbol', $sanction->currency->symbol)->first();

                if ($currency) {
                    try {
                        $sanction->fine = $sanction->fine / $currency->value;
                    } catch (\Throwable $th) {
                    }
                }
            }

            $components = collect();
            $sanction->statements->each(function ($statement) use (&$components) {
                if (!$components->contains('id', $statement->component->id)) {
                    $components->push([
                        'id' => $statement->component->id,
                        'code' => $statement->component->code,
                        'name_en' => $statement->component->name_en,
                        'name_se' => $statement->component->name_se,
                        'desc_en' => $statement->component->desc_en,
                        'desc_se' => $statement->component->desc_se,
                    ]);
                }
                $statement->makeVisible('subcode');
            });

            $sanction->components = $components;
            $sanction->makeVisible('components');
        }

        App::setlocale($locale);
        $r = ['sanctions' => $sanctions, 'draw' => $request->get('draw'), 'recordsTotal' => $sanctionsTotal, 'recordsFiltered' => $sanctionsFiltered];
        $r = collect($r);
        return $r;
    }

    /**
     * Return a specific sanction model
     *
     * Undocumented function long description
     *
     * @param string $locale app locale
     * @param \App\Models\Sanction $sanction
     * @return Illuminate\Database\Eloquent\Collection
     **/
    public function sanctionsShow($locale, Sanction $sanction)
    {
        $sanction->load(['articles', 'dpa', 'user', 'sni', 'type', 'statements', 'tags', 'sanctionFiles', 'outcome'])->makeVisible(['articles', 'articlesSorted', 'created_at_for_humans', 'started_at_for_humans', 'decided_at_for_humans', 'published_at_for_humans', 'dpa', 'url', 'etid', 'updated_at_for_humans', 'user', 'party', 'sni', 'type', 'source', 'statements', 'tags', 'sanctionFiles', 'outcome', 'fine_eur']);
        $sanction->articlesSorted = $sanction->articles->sortBy('title')->values();
        $sanction->dpa->load('country')->makeVisible(['country', 'name']);
        $components = collect();
        $sanction->statements->each(function ($statement) use ($locale, &$components) {
            if (!$components->contains('id', $statement->component->id)) {
                $components->push([
                    'id' => $statement->component->id,
                    'code' => $statement->component->code,
                    'name_en' => $statement->component->name_en,
                    'name_se' => $statement->component->name_se,
                    'desc_en' => $statement->component->desc_en,
                    'desc_se' => $statement->component->desc_se,
                ]);
            }
            $statement->makeVisible('subcode');
        });
        $sanction->components = $components;
        $sanction->makeVisible('components');

        return $sanction;
    }

    public function sanctionsStats($locale, $by)
    {
        return match ($by) {
            'component' => $this->sanctionsByCmponent($locale),
            'statement' => $this->sanctionsByStatement(),
            'chronological' => $this->sanctionsImposedOverTime(),
            'country' => $this->sanctionsByCountry(),
            'sector' => $this->sanctionsBySector($locale),
            'individual' => $this->sanctionsIndividual($locale),
            default => [],
        };
    }

    /**
     * Return all sanctions for the act route in ajax datatable format
     *
     * Undocumented function long description
     *
     * @param string $locale app locale
     * @return Illuminate\Database\Eloquent\Collection
     **/
    public function sanctionsTable($locale, Request $request)
    {
        $org = Organisation::find(session('selected_org')['id']);
        $filterByValue = $request->post('filters')['value'] ?? null;
        $filterByDpa = $request->post('filters')['dpa_id'] ?? null;
        $filterByCountry = $request->post('filters')['country_id'] ?? null;
        $filterBySni = $request->post('filters')['sni_id'] ?? null;
        $filterByOutcome = $request->post('filters')['outcome_id'] ?? null;
        $filterByTag = $request->post('filters')['tag_ids'] ?? null;
        $filterByComponent = $request->post('filters')['component_id'] ?? null;
        $filterByStatement = $request->post('filters')['statement_id'] ?? null;
        $orderBy = $request->post('order')[0]['column'] ?? null;
        $orderDir = $request->post('order')[0]['dir'] ?? null;

        $needle = $request->search['value'];
        // spider search
        $sanctions = Sanction::select('sanctions.*')
            ->when($needle, function ($query) use ($needle) {
                $query->leftJoin('snis', 'sanctions.sni_id', '=', 'snis.id')
                    ->where('title', 'like', '%' . $needle . '%')
                    ->orWhereDate('started_at', 'like', '%' . $needle . '%')
                    ->orWhereDate('decided_at', 'like', '%' . $needle . '%')
                    ->orWhere('published_at', 'like', '%' . $needle . '%')
                    ->orWhere('fine', 'like', '%' . $needle . '%')
                    ->orWhere('sanctions.title', 'like', "%$needle%")
                    ->orWhereRelation('dpa', 'title', 'like', "Category:%$needle%")
                    ->orWhereRaw('LOWER(sanctions.desc_en) LIKE ?', "{\"ops\":[{\"insert\":\"%" . strtolower($needle) . "%")
                    ->orWhereRaw('LOWER(sanctions.desc_se) LIKE ?', "{\"ops\":[{\"insert\":\"%" . strtolower($needle) . "%")
                    ->orwhere('snis.desc_en', $needle)
                    ->orwhere('snis.desc_se', $needle);
            })
            ->when($filterByValue, function ($query) use ($filterByValue, $org) {
                $query->whereHas('statements.deeds', function ($query) use ($filterByValue, $org) {
                    $query->where('value', $filterByValue)
                        ->where('organisation_id', $org->id);
                });
            })
            ->when($filterByDpa, function ($query, $filterByDpa) {
                $query->where('dpa_id', $filterByDpa);
            })
            ->when($filterByCountry, function ($query, $filterByCountry) {
                $query->whereRelation('dpa', 'country_id', $filterByCountry);
            })
            ->when($filterBySni, function ($query, $filterBySni) {
                $query->where('sni_id', $filterBySni);
            })
            ->when($filterByOutcome, function ($query, $filterByOutcome) {
                $query->where('outcome_id', $filterByOutcome);
            })
            ->when($filterByTag, function ($query, $filterByTag) {
                $query->join('sanction_tag', 'sanction_tag.sanction_id', '=', 'sanctions.id')
                    ->whereIn('sanction_tag.tag_id', $filterByTag);
            })
            ->when($filterByComponent, function ($query, $filterByComponent) {
                $query->whereRelation('statements', 'statements.component_id', $filterByComponent);
            })
            ->when($filterByStatement, function ($query, $filterByStatement) {
                $query->whereRelation('statements', 'statements.id', $filterByStatement);
            })
            ->get();

        $colors = ['#ea5455', '#ff5f43', '#ff9f43', '#cab707', '#28c76f'];
        $sanctions = $sanctions->map(function ($sanction) use ($org, $colors) {
            $sanction->statements = $sanction->statements->map(function ($statement) use ($org, $colors) {
                $statement->deed = $statement->organisationDeed($org);
                if ($statement->deed) {
                    $statement->deed->color = $colors[$statement->deed->value - 1];
                    $statement->deed->makeVisible('color');
                }
                return $statement->makeVisible(['subcode', 'deed', 'component']);
            })->sortBy('subcode', SORT_NATURAL);
            return $sanction;
        });

        if ($orderBy == 6) {
            $sanctions = $sanctions->sortBy([
                function ($a, $b) use ($orderDir) {
                    if ($a->statements->pluck('deed.value')->filter()->isNotEmpty() && $b->statements->pluck('deed.value')->filter()->isNotEmpty()) {
                        if ($a->statements->pluck('deed.value')->min() === $b->statements->pluck('deed.value')->min()) {
                            if ($orderDir == 'asc') {
                                return $a->statements->pluck('deed.value')->avg() <=> $b->statements->pluck('deed.value')->avg();
                            } else {
                                return $b->statements->pluck('deed.value')->avg() <=> $a->statements->pluck('deed.value')->avg();
                            }
                        } elseif ($a->statements->pluck('deed.value')->min() < $b->statements->pluck('deed.value')->min()) {
                            return $orderDir == 'asc' ? -1 : 1;
                        } else {
                            return $orderDir == 'asc' ? 1 : -1;
                        }
                    } elseif ($a->statements->pluck('deed.value')->filter()->isEmpty() && $b->statements->pluck('deed.value')->filter()->isEmpty()) {
                        return 1;
                    } elseif ($a->statements->pluck('deed.value')->filter()->isEmpty()) {
                        return 1;
                    } else {
                        return -1;
                    }
                }
            ]);
        }

        $draw = $request->draw;
        $recordsTotal = $sanctions->count();
        $recordsFiltered = $sanctions->count();
        $data = $sanctions->chunk($request->length);
        if (is_int($request->start / $request->length)) {
            $data = $data[$request->start / $request->length] ?? $data;
        } else {
            $data = $data[count($data) - 1] ?? $data;
        }
        $data = $data->load(['articles', 'currency', 'dpa'])->makeVisible(['articles', 'articlesSorted', 'currency', 'created_at_for_humans', 'decided_at_for_humans', 'dpa', 'url', 'party', 'statements', 'fine_eur'])->take($request->length);
        foreach ($data as $sanction) {
            $articles = $sanction->articles;
            $sanction->articlesSorted = $articles->sortBy('title')->values();
            $sanction->dpa->load('country')->makeVisible(['country', 'name']);
        }

        $data = $data->values();
        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'index' => $request->start,
        ];
    }

    public function tags($locale)
    {
        App::setlocale($locale);
        $tags = Tag::all();
        $messages = Lang::get('messages');

        return collect(['tags' => $tags, 'messages' => $messages]);
    }

    public function taskStatuses()
    {
        return TaskStatus::all();
    }

    public function tasks($locale, $year)
    {
        App::setLocale($locale);

        $since = Carbon::createFromDate($year, 1, 1);
        $until = Carbon::createFromDate($year, 12, 31);
        $org = Organisation::find(session('selected_org')['id']);
        $tasks = Task::with(['taskStatus', 'action.actionType'])
            ->where(function ($query) use ($org) {
                $query->whereIn('created_by', $org->users->pluck('id'));
            })
            ->whereDate('start', '>=', $since)
            ->whereDate('start', '<=', $until)
            ->orderBy('start')
            ->get();

        $tasks = $tasks->filter(function ($task) {
            return $task->action?->actionType->role == auth()->user()->role;
        });

        $actionColors = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark'];
        $result = [];
        $tasks->each(function ($task) use ($locale, $actionColors, &$result) {
            $task->can_update = Gate::allows('update', $task);
            $task->can_delete = Gate::allows('delete', $task);
            if ($task->action && $task->action->deleted_at === null) {
                $actionName = $task->action->actionType->{"name_$locale"};
                if (!isset($result[$actionName]['color'])) {
                    $result[$actionName]['color'] = $actionColors[$task->action->actionType->id - 1] ?? null;
                }
                $result[$actionName]['tasks'][] = $task;
            }
        });

        return $result;
    }

    public function tasksForWheel($locale, $year)
    {
        $yearStart = Carbon::createFromDate($year)->startOfYear();
        $yearEnd = Carbon::createFromDate($year)->endOfYear();
        $localeForCarbon = $locale == 'se' ? 'sv-SE' : $locale;
        Carbon::setLocale($localeForCarbon);
        $org = Organisation::find(session('selected_org')['id']);

        $tasks = Task::with(['taskStatus', 'action.actionType'])
            ->where(function ($query) use ($org) {
                $query->whereIn('created_by', $org->users->pluck('id'));
            })
            ->where(function ($query) use ($yearStart, $yearEnd) {
                $query->where(function ($query) use ($yearStart, $yearEnd) {
                    $query->whereDate('start', '>=', $yearStart)
                        ->whereDate('start', '<=', $yearEnd);
                })->orWhere(function ($query) use ($yearStart, $yearEnd) {
                    $query->whereDate('end', '>=', $yearStart)
                        ->whereDate('end', '<=', $yearEnd);
                });
            })
            ->get();

        $tasks = $tasks->filter(function ($task) {
            return $task->action?->actionType->role == auth()->user()->role;
        });

        $tasksGrouped = collect();
        foreach ($tasks as $task) {
            $taskStart = Carbon::parse($task->start);
            $taskEnd = Carbon::parse($task->end);
            $added = false;

            if ($task->start < $yearStart && $taskEnd->between($yearStart, $yearEnd)) {
                $task->start = Carbon::createFromDate($year, 1, 1);
            }

            if ($task->end > $yearEnd && $taskStart->between($yearStart, $yearEnd)) {
                $task->end = Carbon::createFromDate($year, 12, 31);
            }

            $tasksGrouped->each(function ($group) use ($locale, $task, $taskStart, $taskEnd, &$added) {
                if ($added) {
                    return;
                }

                $overlapExists = $group->contains(function ($item) use ($task, $taskStart, $taskEnd) {
                    $itemStart = Carbon::parse($item['start']);
                    $itemEnd = Carbon::parse($item['end']);
                    return $taskStart->between($itemStart, $itemEnd) ||
                        $taskEnd->between($itemStart, $itemEnd) ||
                        ($taskStart->lte($itemStart) && $taskEnd->gte($itemStart));
                });

                if (!$overlapExists) {
                    $group->push([
                        'id' => $task->id,
                        'title' => $task->{"title_$locale"},
                        'start' => $task->start_for_humans,
                        'end' => $task->end_for_humans,
                        'color' => $task->taskStatus->color
                    ]);
                    $added = true;
                }
            });

            if (!$added) {
                $tasksGrouped->push(collect([[
                    'id' => $task->id,
                    'title' => $task->{"title_$locale"},
                    'start' => $task->start_for_humans,
                    'end' => $task->end_for_humans,
                    'color' => $task->taskStatus->color
                ]]));
            }
        }

        return $tasksGrouped;
    }

    public function templates($locale)
    {
        return Template::orderBy("name_$locale")->get();
    }

    public function componentSanctionsTable(Request $request, $locale)
    {
        $start = $request->get('start');
        $length = $request->get('length');
        $searchVal = $request->get('search')['value'];

        $sanctions = Sanction::select('sanctions.*')
            ->join('sanction_statement', 'sanctions.id', '=', 'sanction_statement.sanction_id')
            ->join('statements', 'sanction_statement.statement_id', '=', 'statements.id')
            ->join('components', 'statements.component_id', '=', 'components.id')
            ->where('components.code', $searchVal)
            ->skip($start)
            ->take($length)
            ->get();

        $sanctionsTotal = Sanction::count();
        $sanctionsFiltered = Sanction::select('sanctions.*')
            ->join('sanction_statement', 'sanctions.id', '=', 'sanction_statement.sanction_id')
            ->join('statements', 'sanction_statement.statement_id', '=', 'statements.id')
            ->join('components', 'statements.component_id', '=', 'components.id')
            ->where('components.code', $searchVal)
            ->count();

        $sanctions->load(['articles', 'dpa', 'user'])->makeVisible(['articles', 'articlesSorted', 'created_at_for_humans', 'started_at_for_humans', 'decided_at_for_humans', 'published_at_for_humans', 'dpa', 'url', 'etid', 'updated_at_for_humans', 'user', 'party']);

        foreach ($sanctions as $sanction) {
            $articles = $sanction->articles;
            $sanction->articlesSorted = $articles->sortBy('title')->values();
            $sanction->dpa->load('country')->makeVisible(['country', 'name']);

            if ($sanction->currency?->symbol && $sanction->currency->symbol != 'EUR') {
                $currency = Currency::where('symbol', $sanction->currency->symbol)->first();

                if ($currency) {
                    try {
                        $sanction->fine = $sanction->fine / $currency->value;
                    } catch (\Throwable $th) {
                    }
                }
            }
        }

        App::setlocale($locale);
        $r = ['sanctions' => $sanctions, 'draw' => $request->get('draw'), 'recordsTotal' => $sanctionsTotal, 'recordsFiltered' => $sanctionsFiltered];
        $r = collect($r);
        return $r;
    }

    public function statementSanctionsTable(Request $request, $locale)
    {
        $start = $request->get('start');
        $length = $request->get('length');
        $searchVal = $request->get('search')['value'];

        $sanctions = Sanction::select('sanctions.*')
            ->join('sanction_statement', 'sanctions.id', '=', 'sanction_statement.sanction_id')
            ->join('statements', 'sanction_statement.statement_id', '=', 'statements.id')
            ->join('components', 'statements.component_id', '=', 'components.id')
            ->whereRaw("CONCAT(components.code, '.', statements.code) = ?", $searchVal)
            ->skip($start)
            ->take($length)
            ->get();

        $sanctionsTotal = Sanction::count();
        $sanctionsFiltered = Sanction::select('sanctions.*')
            ->join('sanction_statement', 'sanctions.id', '=', 'sanction_statement.sanction_id')
            ->join('statements', 'sanction_statement.statement_id', '=', 'statements.id')
            ->join('components', 'statements.component_id', '=', 'components.id')
            ->whereRaw("CONCAT(components.code, '.', statements.code) = ?", $searchVal)
            ->count();

        $sanctions->load(['articles', 'dpa', 'user'])->makeVisible(['articles', 'articlesSorted', 'created_at_for_humans', 'started_at_for_humans', 'decided_at_for_humans', 'published_at_for_humans', 'dpa', 'url', 'etid', 'updated_at_for_humans', 'user', 'party']);

        foreach ($sanctions as $sanction) {
            $articles = $sanction->articles;
            $sanction->articlesSorted = $articles->sortBy('title')->values();
            $sanction->dpa->load('country')->makeVisible(['country', 'name']);

            if ($sanction->currency?->symbol && $sanction->currency->symbol != 'EUR') {
                $currency = Currency::where('symbol', $sanction->currency->symbol)->first();

                if ($currency) {
                    try {
                        $sanction->fine = $sanction->fine / $currency->value;
                    } catch (\Throwable $th) {
                    }
                }
            }
        }

        App::setlocale($locale);
        $r = ['sanctions' => $sanctions, 'draw' => $request->get('draw'), 'recordsTotal' => $sanctionsTotal, 'recordsFiltered' => $sanctionsFiltered];
        $r = collect($r);
        return $r;
    }

    private function sanctionsByCmponent($locale)
    {
        $sanctions = Sanction::select(DB::raw("CONCAT(components.code, ' - ', components.name_$locale) AS name, SUM(fine / currencies.value) AS sum"))
            ->join('sanction_statement', 'sanctions.id', '=', 'sanction_statement.sanction_id')
            ->join('statements', 'sanction_statement.statement_id', '=', 'statements.id')
            ->join('components', 'statements.component_id', '=', 'components.id')
            ->join('currencies', 'sanctions.currency_id', '=', 'currencies.id')
            ->groupBy('name')
            ->orderBy('sum', 'desc')
            ->get();

        $data = $sanctions->pluck('sum');
        $data = $data->map(function ($fine) {
            return round($fine);
        });

        $sum = ['categories' => $sanctions->pluck('name'), 'data' => $data];

        $sanctions = Sanction::select(DB::raw("CONCAT(components.code, ' - ', components.name_$locale) AS name, COUNT(1) AS count"))
            ->join('sanction_statement', 'sanctions.id', '=', 'sanction_statement.sanction_id')
            ->join('statements', 'sanction_statement.statement_id', '=', 'statements.id')
            ->join('components', 'statements.component_id', '=', 'components.id')
            ->groupBy('name')
            ->orderBy('count', 'desc')
            ->get();

        $count = ['categories' => $sanctions->pluck('name'), 'data' => $sanctions->pluck('count')];

        return ['sum' => $sum, 'count' => $count];
    }

    private function sanctionsByStatement()
    {
        $sanctions = Sanction::select(DB::raw("CONCAT(components.code, '.', statements.code) AS code, SUM(fine / currencies.value) AS sum"))
            ->join('sanction_statement', 'sanctions.id', '=', 'sanction_statement.sanction_id')
            ->join('statements', 'sanction_statement.statement_id', '=', 'statements.id')
            ->join('components', 'statements.component_id', '=', 'components.id')
            ->join('currencies', 'sanctions.currency_id', '=', 'currencies.id')
            ->groupBy(DB::raw("CONCAT(components.code, '.', statements.code)"))
            ->orderBy('sum', 'desc')
            ->get();

        $data = $sanctions->pluck('sum');
        $data = $data->map(function ($fine) {
            return round($fine);
        });

        $sum = ['categories' => $sanctions->pluck('code'), 'data' => $data];

        $sanctions = Sanction::select(DB::raw("CONCAT(components.code, '.', statements.code) AS code, COUNT(1) AS count"))
            ->join('sanction_statement', 'sanctions.id', '=', 'sanction_statement.sanction_id')
            ->join('statements', 'sanction_statement.statement_id', '=', 'statements.id')
            ->join('components', 'statements.component_id', '=', 'components.id')
            ->groupBy(DB::raw("CONCAT(components.code, '.', statements.code)"))
            ->orderBy('count', 'desc')
            ->get();

        $count = ['categories' => $sanctions->pluck("code"), 'data' => $sanctions->pluck('count')];

        return ['sum' => $sum, 'count' => $count];
    }

    private function sanctionsImposedOverTime()
    {
        $sanctions = Sanction::select(DB::raw("YEAR(sanctions.decided_at) AS year, MONTH(sanctions.decided_at) AS month, DATE_FORMAT(sanctions.decided_at, '%b %Y') AS month_year, SUM(fine / currencies.value) AS sum, COUNT(1) AS count"))
            ->join('currencies', 'sanctions.currency_id', '=', 'currencies.id')
            ->whereNotNull('decided_at')
            ->groupBy('year', 'month', 'month_year')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        $sanctions = $sanctions->map(function ($sanction) {
            $sanction->sum = round($sanction->sum);

            return $sanction;
        });

        $sum = ['categories' => $sanctions->pluck('month_year'), 'data' => $sanctions->pluck('sum')];
        $count = ['categories' => $sanctions->pluck('month_year'), 'data' => $sanctions->pluck('count')];

        return ['sum' => $sum, 'count' => $count];
    }

    private function sanctionsByCountry()
    {
        $sanctions = Sanction::select('countries.name', DB::raw('SUM(fine / currencies.value) AS sum'))
            ->join('dpas', 'sanctions.dpa_id', '=', 'dpas.id')
            ->join('countries', 'dpas.country_id', '=', 'countries.id')
            ->join('currencies', 'sanctions.currency_id', '=', 'currencies.id')
            ->groupBy('countries.name')
            ->orderBy('sum', 'desc')
            ->take(10)
            ->get();

        $data = $sanctions->pluck('sum');
        $data = $data->map(function ($fine) {
            return round($fine);
        });

        $sum = ['categories' => $sanctions->pluck('name'), 'data' => $data];

        $sanctions = Sanction::select('countries.name', DB::raw('COUNT(1) AS count'))
            ->join('dpas', 'sanctions.dpa_id', '=', 'dpas.id')
            ->join('countries', 'dpas.country_id', '=', 'countries.id')
            ->groupBy('countries.name')
            ->orderBy('count', 'desc')
            ->take(10)
            ->get();

        $count = ['categories' => $sanctions->pluck('name'), 'data' => $sanctions->pluck('count')];

        return ['sum' => $sum, 'count' => $count];
    }

    private function sanctionsBySector($locale)
    {
        $sanctions = Sanction::select("snis.desc_$locale", DB::raw('SUM(fine / currencies.value) AS sum'))
            ->join('snis', 'sanctions.sni_id', '=', 'snis.id')
            ->join('currencies', 'sanctions.currency_id', '=', 'currencies.id')
            ->groupBy("snis.desc_$locale")
            ->orderBy('sum', 'desc')
            ->get();

        $data = $sanctions->pluck('sum');
        $data = $data->map(function ($fine) {
            return round($fine);
        });

        $sum = ['categories' => $sanctions->pluck("desc_$locale"), 'data' => $data];

        $sanctions = Sanction::select("snis.desc_$locale", DB::raw('COUNT(1) AS count'))
            ->join('snis', 'sanctions.sni_id', '=', 'snis.id')
            ->groupBy("snis.desc_$locale")
            ->orderBy('count', 'desc')
            ->get();

        $count = ['categories' => $sanctions->pluck("desc_$locale"), 'data' => $sanctions->pluck('count')];

        return ['sum' => $sum, 'count' => $count];
    }

    private function sanctionsIndividual($locale)
    {
        $sanctions = Sanction::select('sanctions.title', 'sanctions.party', 'sanctions.decided_at', "snis.desc_$locale AS sector", 'countries.name AS country', "types.text_$locale AS type", DB::raw('SUM(fine / currencies.value) AS sum'))
            ->join('snis', 'sanctions.sni_id', '=', 'snis.id')
            ->join('dpas', 'sanctions.dpa_id', '=', 'dpas.id')
            ->join('countries', 'dpas.country_id', '=', 'countries.id')
            ->join('types', 'sanctions.type_id', '=', 'types.id')
            ->join('currencies', 'sanctions.currency_id', '=', 'currencies.id')
            ->groupBy('sanctions.title', 'sanctions.party', 'sanctions.decided_at', "snis.desc_$locale", 'countries.name', "types.text_$locale")
            ->orderBy('sum', 'desc')
            ->get();

        $sanctions = $sanctions->map(function ($sanction) {
            $sanction->decided_at = $sanction->decided_at_for_humans;
            $sanction->sum = round($sanction->sum);

            return $sanction;
        });

        return $sanctions->makeVisible(['party', 'sector', 'country', 'type', 'sum']);
    }

    public function statements($locale)
    {
        return Statement::all()
            ->sortBy('subcode', SORT_NATURAL)
            ->makeVisible(['subcode'])
            ->makeHidden(['code', 'content_en', 'content_se', 'desc_en', 'desc_se', 'k1_en', 'k1_se', 'k2_en', 'k2_se', 'k3_en', 'k3_se', 'k4_en', 'k4_se', 'k5_en', 'k5_se', 'implementation_en', 'implementation_se', 'guide_en', 'guide_se', 'sort_order']);
    }
}
