<?php

namespace App\Http\Controllers;

use App\Mobile;
use App\Product;
use App\Record;
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
        Log::info($request->all());
        $httpClient = new CurlHTTPClient(env('LINEBOT_TOKEN'));
        $bot = new LINEBot($httpClient, ['channelSecret' => env('LINEBOT_SECRET')]);
        $text = $request->events[0]['message']['text'];
        $user_id = $request->events[0]['source']['userId'];
        $dialog = $this->dialog($text);
        Log::debug("dialog = $dialog");
        Log::debug(strpos($dialog, 'Fallback'));
        if (count(Mobile::where('userId', $user_id)->get()) > 0) {
            $status = Mobile::where('userId', $user_id)->first()->status;
            if ($status == 3) {
                if (strpos($dialog, 'Fallback')!==false) {

                    $reply = "請問您想要找什麼樣的手機? ex:iphone 6s";
                }else{
                    Record::create([
                        'userId' => $user_id,
                        'key' => 'model',
                        'value' => $text,
                    ]);
                    $param = Record::all()->toArray();
                    $http = new Client();
                    $response = $http->get('https://ptt-crawler-gdg.herokuapp.com/posts', $param);
                    $replies = json_decode($response->getBody()->getContents());
                    $replies = array_map(function ($p) {
                        return ['title' => $p->title, 'url' => $p->url];
                    }, $replies);
                    Log::debug($replies);
                    $msg = new MultiMessageBuilder();
                    foreach ($replies as $reply) {
                        $_msg = new TextMessageBuilder($reply);
                        $msg->add($_msg);
                    }
                    DB::table('mobiles')->truncate();
                    DB::table('records')->truncate();

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
                        'key' => 'country',
                        'value' => $text,
                    ]);
                }
            } else if ($status == 1) {
                if (strpos($dialog, 'Fallback')!==false) {
                    $reply = "您好，很高興為您服務。請問您想要購買或賣出手機?";
                    $reply = new TextMessageBuilder($reply);
                    $bot->replyMessage($request->events[0]['replyToken'], $reply);
                    return;
                } elseif (strpos($text, '賣')) {
                    $text = "買";
                } elseif (strpos($text, '買')) {
                    $text = "買";

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
            Log::debug('Succeeded!');
            return;
        }
    }

    function test(Request $request)
    {
        Log::info($request->all());

//        $client = new Client();
//        $res_to = $client->request('POST', env('PASS_URL'), [
//            'form_params' => [
//                'title' => $request->title,
//                'url' => $request->url,
//                'country' => $request->country,
//                'zone' => $request->zone,
//                'model' => $request->model,
//                'type' => $request->type,
//                'date' => $request->date,
//                'price' => $request->price,
//            ]
//        ]);
//        $response = (string)$res_to->getBody();
//        return $response;

        $httpClient = new CurlHTTPClient(env('LINEBOT_TOKEN'));
        $bot = new LINEBot($httpClient, ['channelSecret' => env('LINEBOT_SECRET')]);
        $text = $request->events[0]['message']['text'];


        $replies = [
            'title' => "title:" . $request->title,
            'url' => "url:" . $request->url,
            'country' => "country:" . $request->country,
            'zone' => "zone:" . $request->zone,
            'model' => "model:" . $request->model,
            'type' => "type:" . $request->type,
            'date' => "date:" . $request->date,
            'price' => "price:" . $request->price,
        ];
        $reply = "請輸入欲查詢的地區";

        $msg = new MultiMessageBuilder();
        foreach ($replies as $reply) {
            $_msg = new TextMessageBuilder($reply);
            $msg->add($_msg);
        }


        $response = $bot->replyMessage($request->events[0]['replyToken'], $msg);
        if ($response->isSucceeded()) {
            Log::debug('Succeeded!');
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
    public function area(Request $request)
    {
        $http = new Client();
        $response = $http->get('https://ptt-crawler-gdg.herokuapp.com/posts');
        $getbody = json_decode($response ->getBody()->getContents());
        $getbody=array_map(function ($resp){
            try{
                return [
                    'title' => $resp->title,
                    'url'=>$resp->url
                ];
            }catch (Exception $e){
                return [
                    'title' => '',
                    'url' => ''
                ];
            }
        }, $getbody);
        $getbody=array_filter($getbody,function ($p){
            return $p['title']!='' && $p['url']!='';
        });
        return response()->json([$getbody]);
    }

}
