<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Currency;
use App\Models\Dpa;
use App\Models\Outcome;
use App\Models\Sanction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

class SanctionLoader
{
    public function loadSanctions()
    {
        $response = Http::get('https://gdprhub.eu/api.php', [
            'action' => 'query',
            'format' => 'json',
            'list' => 'categorymembers',
            'cmtitle' => 'Category:DPA_Decisions',
            'cmprop' => 'ids|title|type',
            'cmtype' => 'subcat',
            'cmlimit' => 'max',
            'cmsort' => 'sortkey',
            'cmdir' => 'newer'
        ]);
        if ($response->successful()) {
            $data = json_decode($response->body());
            try {
                DB::transaction(function () use ($data) {
                    foreach ($data->query->categorymembers as $dpa) {
                        // exists?
                        $exists = Dpa::where('pageid', $dpa->pageid)->first();
                        if (!$exists) {
                            Dpa::create(['pageid' => $dpa->pageid, 'title' => $dpa->title]);
                        }
                    }
                });
                // begin subcats
                $dpas = Dpa::all();
                $data = [];
                foreach ($dpas as $dpa) {
                    $response = Http::get('https://gdprhub.eu/api.php', [
                        'action' => 'query',
                        'format' => 'json',
                        'list' => 'categorymembers',
                        'cmtitle' => $dpa->title,
                        'cmprop' => 'ids|title|type',
                        'cmtype' => 'page',
                        'cmlimit' => 'max',
                        'cmsort' => 'sortkey',
                        'cmdir' => 'newer'
                    ]);
                    $data = json_decode($response->body());
                    $data = $data->query->categorymembers;
                    foreach ($data as $sanction) {
                        // exists?
                        $exists = Sanction::where('pageid', $sanction->pageid)->first();
                        if (!$exists) {
                            $sanction = Sanction::create(['pageid' => $sanction->pageid, 'title' => $sanction->title, 'dpa_id' => $dpa->id, 'desc_en' => null, 'desc_se' => null, 'sni_id' => null, 'type_id' => null, 'outcome_id' => null, 'party' => null, 'issue_category_id' => null, 'etid' => null, 'user_id' => null, 'source' => null]);
                            // fields
                            $parseResponse = Http::get('https://gdprhub.eu/api.php', [
                                'action' => 'parse',
                                'format' => 'json',
                                'pageid' => $sanction->pageid,
                            ]);
                            if ($parseResponse->successful()) {
                                // columns [decided_at]
                                $parseData = json_decode($parseResponse->body());
                                $html = json_decode(json_encode($parseData->parse->text), true);
                                $html = $html['*'];
                                $sanction->update(['html' => $html]);
                                $matches = [];
                                $dateDecided = preg_match('/<td>Decided:<\/td>\n<td>(\d*.*)\n/m', $html, $matches);
                                if (isset($matches[1])) {
                                    if ($matches[1] != null && $matches[1] != '' && strlen($matches[1]) > 7) {
                                        try {
                                            $sanction->update(['decided_at' => Carbon::parse($matches[1])]);
                                        } catch (\Throwable $th) {
                                            //throw $th;
                                        }
                                    }
                                }
                                // fine & currency
                                $fineMatches = [];
                                $fineRegex = preg_match('/<td>Fine:<\/td>\n<td>(\d*.*)\s(\w+)\n/m', $html, $fineMatches);
                                // does it have a fine?
                                if (isset($fineMatches[1])) {
                                    // is it legible?
                                    if ($fineMatches[1] != null && $fineMatches[1] != '') {
                                        // strip the digits in case of commas
                                        $fine = (int)filter_var($fineMatches[1], FILTER_SANITIZE_NUMBER_INT);
                                        $sanction->update(['fine' => $fine]);
                                    }
                                }
                                // has currency?
                                if (isset($fineMatches[2])) {
                                    // is it legible?
                                    if ($fineMatches[2] != null && $fineMatches[2] != '') {
                                        // does it exist
                                        $currency = Currency::where('symbol', $fineMatches[2])->first();
                                        if ($currency) {
                                            $sanction->update(['currency_id' => $currency->id]);
                                        } else {
                                            // create it
                                            $currency = Currency::create(['symbol' => $fineMatches[2]]);
                                            $sanction->update(['currency_id' => $currency->id]);
                                        }
                                    }
                                }
                                // started_at
                                $startedMatches = [];
                                $startedAtRegex = preg_match('/<td>Started:<\/td>\n<td>(\d*.*)\n/m', $html, $startedMatches);
                                if (isset($startedMatches[1])) {
                                    if ($startedMatches[1] != null && $startedMatches[1] != '' && strlen($startedMatches[1]) > 7) {
                                        try {
                                            $sanction->update(['started_at' => Carbon::parse($startedMatches[1])]);
                                        } catch (\Throwable $th) {
                                            //throw $th;
                                        }
                                    }
                                }
                                // published_at
                                $publishedMatches = [];
                                $publishedAtRegex = preg_match('/<td>Published:<\/td>\n<td>(\d*.*)\n/m', $html, $publishedMatches);
                                if (isset($publishedMatches[1])) {
                                    if ($publishedMatches[1] != null && $publishedMatches[1] != '' && strlen($publishedMatches[1]) > 7) {
                                        try {
                                            $sanction->update(['published_at' => Carbon::parse($publishedMatches[1])]);
                                        } catch (\Throwable $th) {
                                            //throw $th;
                                        }
                                    }
                                }
                                // articles
                                // get relevant laws if any
                                $relevantLawMatches = [];
                                $relevantRegex = preg_match('/<td>Relevant Law:<\/td>\n<td>(.*)\n<\/td>/m', $html, $relevantLawMatches);
                                // has law?
                                if (isset($relevantLawMatches[1])) {
                                    // law is not empty or weird space?
                                    if ($relevantLawMatches[1] != null && $relevantLawMatches[1] != '') {
                                        $relevantHtml = $relevantLawMatches[1];
                                        $articleMatches = [];
                                        $articleRegex = preg_match_all('/href="(?P<link>[^"]*)"[^>]*>(?P<title>[^<]*)/m', $relevantHtml, $articleMatches);
                                        if (isset($articleMatches[1])) {
                                            if ($articleMatches[1] != null && $articleMatches[1] != '') {
                                                for ($i = 0; $i < count($articleMatches['link']); $i++) {
                                                    // find if article exists
                                                    $article = Article::where('title', $articleMatches['title'][$i])->first();
                                                    if (!$article) {
                                                        // fix link
                                                        $linkPrefix = substr($articleMatches['link'][$i], 0, 6);
                                                        if ($linkPrefix != '/index') {
                                                            $url = $articleMatches['link'][$i];
                                                        } else {
                                                            $url = 'https://gdprhub.eu/' . $articleMatches['link'][$i];
                                                        }
                                                        // fix title
                                                        if (strlen($articleMatches['title'][$i]) > 128) {
                                                            $title = substr($articleMatches['title'][$i], 0, 125) . '...';
                                                        } else {
                                                            $title = $articleMatches['title'][$i];
                                                        }
                                                        $article = Article::create(['title' => mb_convert_encoding($title, 'UTF-8'), 'url' => $url]);
                                                    } else {
                                                        $sanction->articles()->attach($article);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                // outcome
                                $outcomeMatches = [];
                                preg_match('/<td>Outcome:<\/td>\n<td>(.*)\n/m', $html, $outcomeMatches);

                                if (isset($outcomeMatches[1])) {
                                    $outcome = Outcome::where('desc_en', $outcomeMatches[1])->first();

                                    if ($outcome) {
                                        try {
                                            $sanction->update(['outcome_id' => $outcome->id]);
                                        } catch (\Throwable $th) {

                                        }
                                    }
                                }
                                // source
                                $sourceMatches = [];
                                preg_match('/<td>Original Source:<\/td>\n<td><a.*href=\"(.*)\".*\n/m', $html, $sourceMatches);

                                if (isset($sourceMatches[1])) {
                                    try {
                                        $sanction->update(['source' => $sourceMatches[1]]);
                                    } catch (\Throwable $th) {

                                    }
                                }
                            } else {
                                $parseResponse->throw();
                            }
                        }
                    }
                }
                return $data;
                // end subcats
            } catch (\Throwable $th) {
                throw $th;
            }
            return $response;
        } else {
            $response->throw();
        }
        return 1;
    }
}
