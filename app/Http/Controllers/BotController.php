<?php

namespace App\Http\Controllers;

use App\Mobile;
use App\Product;
use App\Record;
use ErrorException;
use http\Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use GuzzleHttp\Client;
use LINE\LINEBot\RichMenuBuilder;
use LINE\LINEBot\RichMenuBuilder\RichMenuAreaBoundsBuilder;
use LINE\LINEBot\RichMenuBuilder\RichMenuAreaBuilder;
use LINE\LINEBot\RichMenuBuilder\RichMenuSizeBuilder;
use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use App\fc;
use Google\Cloud\Dialogflow\V2\SessionsClient;
use Google\Cloud\Dialogflow\V2\IntentsClient;
use Google\Cloud\Dialogflow\V2\TextInput;
use Google\Cloud\Dialogflow\V2\QueryInput;
use Response;
use Google\Cloud\Dialogflow\V2\Intent;
use Google\Cloud\Dialogflow\V2\Intent\TrainingPhrase\Part;
use Google\Cloud\Dialogflow\V2\Intent\TrainingPhrase;
use Google\Cloud\Dialogflow\V2\Intent\Message;
use Google\Cloud\Dialogflow\V2\Intent\Message\Text;

class BotController extends Controller
{

    function chat(Request $request)
    {
//        Log::info($request->all());
        $httpClient = new CurlHTTPClient(env('LINEBOT_TOKEN'));
        $bot = new LINEBot($httpClient, ['channelSecret' => env('LINEBOT_SECRET')]);
        $text = $request->events[0]['message']['text'];
        $user_id = $request->events[0]['source']['userId'];
        $dialog = $this->dialog($text);
        Log::debug($text."dialog = $dialog");
//        Log::debug(strpos($dialog, 'Fallback'));
        if (count(Mobile::where('userId', $user_id)->get()) > 0) {
            $status = Mobile::where('userId', $user_id)->first()->status;
            if ($status == 4) {
                if (is_numeric($text) == 0) {
//                    Log::debug(is_numeric($text));
                    $reply = "請問您想搜尋的價格為?";
                }else{
//                    Log::debug("price".(int)$text);
                    $price_gte = (int)$text - 5000;
                    $price_lte = (int)$text + 10000;
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
                    $param=[];
                    foreach (Record::all()->toArray() as $rec) {
                        $param[$rec['key']]=$rec['value'];
                    }

                    $param['_limit'] = 5;

                    $http = new Client();
                    $response = $http->request('GET',
                        'https://ptt-crawler-gdg.herokuapp.com/posts',
                        ['query' => $param]);
//                    Log::debug($param);
                    $getbody = json_decode($response ->getBody()->getContents());
                    $getbody = array_map(function ($resp){
                        try{
                            return [
                                'title' => $resp->title,
                                'url'=>$resp->url
                            ];
                        }catch (ErrorException $e){
                            return [
                                'title' => '',
                                'url' => ''
                            ];
                        }
                    }, $getbody);
                    $getbody = array_filter($getbody,function ($p){
                        return $p['title']!='' && $p['url']!='';
                    });
//                    Log::debug($getbody);
                    if(count($getbody) > 0){
                        $msg = new MultiMessageBuilder();

                        foreach ($getbody as $reply) {
                            $_msg = new TextMessageBuilder($reply['title'].' '.$reply['url']);
                            $msg->add($_msg);
                        }

                        $bot->replyMessage($request->events[0]['replyToken'], $msg);
                    }else{
                        $reply = "查無結果";
                        $reply = new TextMessageBuilder($reply);
                        $bot->replyMessage($request->events[0]['replyToken'], $reply);
                    }

                    $users = Mobile::where('userId', $user_id)->get();
                    $records = Record::where('userId', $user_id)->get();
                    foreach ($users as $user){
                        $user->delete();
                    }
                    foreach ($records as $record){
                        $record->delete();
                    }
                    return response()->json([$getbody]);

                }


            } else if ($status == 3) {
                if (strpos($dialog, 'Fallback'!==false)) {
                    $reply = "請問您想要找什麼樣的手機? ex:iphone 6s";
                } else {
                    $reply = "請問您想搜尋的價格為?";
                    $user = Mobile::where('userId', $user_id)->first();
                    $user->update(['text' => $text, 'status' => 4]);
                    Record::create([
                        'userId' => $user_id,
                        'key' => 'title_like',
                        'value' => $text,
                    ]);
                }
            } else if ($status == 2) {
                if (strpos($dialog, 'Fallback'!==false)) {
                    $reply = "請問您要搜尋的縣市為? ex:台北/台中/台南";
                } else {
                    $reply = "請問您想要找什麼樣的手機? ex:iphone 6s";
                    $user = Mobile::where('userId', $user_id)->first();
                    $user->update(['text' => $text, 'status' => 3]);
                    Record::create([
                        'userId' => $user_id,
                        'key' => 'county_like',
                        'value' => $text,
                    ]);
                }
            } else if ($status == 1) {
                if (strpos($dialog, 'Fallback')!==false) {
                    $reply = "您好，很高興為您服務。請問您想要購買或賣出手機?";
                    $reply = new TextMessageBuilder($reply);
                    $bot->replyMessage($request->events[0]['replyToken'], $reply);
                    return;
                } elseif (strpos($text, '賣')!==false) {
                    $text = "buy";
                } elseif (strpos($text, '買')!==false) {
                    $text = "sell";

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
//            Log::debug('Succeeded!');
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
