<?php

namespace App\Http\Controllers;

use App\Mobile;
use App\Product;
use App\Record;
use ErrorException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use GuzzleHttp\Client;
use Google\Cloud\Dialogflow\V2\SessionsClient;
use Google\Cloud\Dialogflow\V2\TextInput;
use Google\Cloud\Dialogflow\V2\QueryInput;
use Response;

class BotController extends Controller
{
    function chat(Request $request)
    {
        $httpClient = new CurlHTTPClient(env('LINEBOT_TOKEN'));
        $bot = new LINEBot($httpClient, ['channelSecret' => env('LINEBOT_SECRET')]);
        $text = $request->events[0]['message']['text'];
        $user_id = $request->events[0]['source']['userId'];
        $dialog = $this->dialog($text);
        $notall = strpos($text, '都可') === false;
        Log::info($text);
        Log::debug($dialog->content());
        if (count(Mobile::where('userId', $user_id)->get()) > 0) {
            $status = Mobile::where('userId', $user_id)->first()->status;
            if ((strpos($dialog->content(), 'Go back to the previous step') && $status != 1)) {
                Log::debug("status = " . $status);
                $status -= 1;
                $user = Mobile::where('userId', $user_id)->first();
                $user->update(['text' => $text, 'status' => $status]);
                Log::debug("status = " . $status);
            }
            if ($status == 5) {
                $users = Mobile::where('userId', $user_id)->get();
                $records = Record::where('userId', $user_id)->get();
                $reply = '感謝您的使用';
                foreach ($users as $user) {
                    $user->delete();
                }
                foreach ($records as $record) {
                    $record->delete();
                }

            } elseif ($status == 4) {
                if (is_numeric($text) == 0 && $notall == 1) {
                    $reply = "請問您想搜尋的價格為?";
                } else {
                    if (!is_numeric($text) == 0) {
                        $price_gte = (int)$text - 2000;
                        $price_lte = (int)$text + 2000;
                        Record::create([
                            'userId' => $user_id,
                            'key' => 'price_lte',
                            'value' => $price_lte,
                        ]);
                        Record::create([
                            'userId' => $user_id,
                            'key' => 'price_gte',
                            'value' => $price_gte,
                        ]);
                    }

                    $param = [];
                    foreach (Record::all()->toArray() as $rec) {
                        $param[$rec['key']] = $rec['value'];
                    }
                    $param['_limit'] = 5;
                    $http = new Client();
                    $response = $http->request('GET',
                        'https://ptt-crawler-gdg.herokuapp.com/posts',
                        ['query' => $param]);
                    $getbody = json_decode($response->getBody()->getContents());
                    $getbody = array_map(function ($resp) {
                        try {
                            return [
                                'title' => $resp->title,
                                'url' => $resp->url,
                                'price' => $resp->price,
                            ];
                        } catch (ErrorException $e) {
                            return [
                                'title' => '',
                                'url' => '',
                                'price' => '',
                            ];
                        }
                    }, $getbody);
                    $getbody = array_filter($getbody, function ($p) {
                        return $p['title'] != '' && $p['price'] != '' && $p['url'] != '';
                    });

                    if (count($getbody) > 0) {
                        $msg = new MultiMessageBuilder();
                        foreach ($getbody as $reply) {
                            $_msg = new TextMessageBuilder($reply['title'] . "\n$" . $reply['price'] . ' ' . "\n" . $reply['url']);
                            $msg->add($_msg);
                        }
                        $bot->replyMessage($request->events[0]['replyToken'], $msg);
                    } else {
                        $reply = "查無結果";
                        $reply = new TextMessageBuilder($reply);
                        $bot->replyMessage($request->events[0]['replyToken'], $reply);
                    }
                    $user = Mobile::where('userId', $user_id)->first();
                    $user->update(['text' => $text, 'status' => 5]);
                    return response()->json([$getbody]);

                }

            } else if ($status == 3) {
                if (!strpos($dialog->content(), 'step3-reply labels - custom') && $notall == 1) {
                    $reply = "請問您想要找什麼樣的手機? ex:iphone 6s";
                } else {
                    if (strpos($dialog->content(), 'step3-reply labels - custom')) {
                        Record::create([
                            'userId' => $user_id,
                            'key' => 'title_like',
                            'value' => $text,
                        ]);
                    }
                    $reply = "請問您想搜尋的價格為?";
                    $user = Mobile::where('userId', $user_id)->first();
                    $user->update(['text' => $text, 'status' => 4]);

                }
            } else if ($status == 2) {
                if (!strpos($dialog->content(), 'step2-reply county') && $notall == 1) {
                    $reply = "請問您要搜尋的縣市為? ex:台北/台中/台南";
                } else {
                    if (strpos($dialog->content(), 'step2-reply county')) {
                        Record::create([
                            'userId' => $user_id,
                            'key' => 'county_like',
                            'value' => $text,
                        ]);
                    }
                    $reply = "請問您想要找什麼樣的手機? ex:iphone 6s";
                    $user = Mobile::where('userId', $user_id)->first();
                    $user->update(['text' => $text, 'status' => 3]);

                }
            } else if ($status == 1) {
                if (!strpos($dialog->content(), 'step1-ask')) {
                    $reply = "您好，很高興為您服務。請問您想要購買或賣出手機?";
                    $reply = new TextMessageBuilder($reply);
                    $bot->replyMessage($request->events[0]['replyToken'], $reply);
                    return;
                } elseif (strpos($dialog->content(), 'step1-ask transaction_buy')) {
                    $text = "sell";
                } elseif (strpos($dialog->content(), 'step1-ask transaction_sale')) {
                    $text = "buy";
                }
                $reply = "請問您要搜尋的縣市為? ex:台北/台中/台南";
                $user = Mobile::where('userId', $user_id)->first();
                $user->update(['text' => $text, 'status' => 2]);
                $reply = new TextMessageBuilder($reply);
                $bot->replyMessage($request->events[0]['replyToken'], $reply);
                Record::create([
                    'userId' => $user_id,
                    'key' => 'type',
                    'value' => $text,
                ]);
            }
        } else {
            $reply = "您好，很高興為您服務。請問您想要購買或賣出手機?";
            $status = 1;
            Mobile::create([
                'userId' => $user_id,
                'text' => $text,
                'status' => $status,
            ]);
        }
        $reply = new TextMessageBuilder($reply);
        $response = $bot->replyMessage($request->events[0]['replyToken'], $reply);

        if ($response->isSucceeded()) {
            return;
        }
    }

    public function dialog($text)
    {
        $credentials = [env('GOOGLE_APPLICATION_CREDENTIALS')];
        $projectName = 'linebot-xvhlqg';
        $sessionsClient = new SessionsClient($credentials);
        $session = $sessionsClient->sessionName($projectName, uniqid());
        $languageCode = 'zh-tw';
// create text input
        $textInput = new TextInput();
        $textInput->setText($text);
        $textInput->setLanguageCode($languageCode);

// create query input
        $queryInput = new QueryInput();
        $queryInput->setText($textInput);

// get response and relevant info
        $response = $sessionsClient->detectIntent($session, $queryInput);
        $queryResult = $response->getQueryResult();
        $intent = $queryResult->getIntent();
        $displayName = $intent->getDisplayName();
        return response()->json($displayName);
    }
}
