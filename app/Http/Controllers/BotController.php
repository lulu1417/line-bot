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

class BotController extends Controller
{
    function store(Request $request){
        Log::info($request->all());
        validator::make($request->all(), [
            'title' => ['required', 'unique:mobiles'],
            'url' => ['required', 'unique:mobiles'],
            'type' => ['required'],
            'month' => ['required'],
            'date' => ['required'],
        ]);
        $create = Mobile::create([
            'title' => $request->title,
            'url' => $request->url,
            'month' => $request->month,
            'date' => $request->date,
            'type' => $request->type,
        ]);
        if($create){
            return response()->json($create);
        }

    }

    function chat(Request $request){

        Log::info($request->all());
        $httpClient = new CurlHTTPClient(env('LINEBOT_TOKEN'));
        $bot = new LINEBot($httpClient, ['channelSecret' => env('LINEBOT_SECRET')]);
        $text = $request->events[0]['message']['text'];
        $user_id = $request->events[0]['source']['userId'];

        if(count(Mobile::where('userId', $user_id)->get()) > 0 ){
            $status = Mobile::where('userId', $user_id)->first()->status;
                if($status == 3){
                    Record::create([
                        'userId' => $user_id,
                        'key' => 'model',
                        'value' => $text,
                    ]);
                    $param = Record::all()->toArray();
                    $http = new Client();
                    $response = $http->get('https://ptt-crawler-gdg.herokuapp.com/posts', $param);
                    $replies = json_decode($response->getBody()->getContents());
                    $replies = array_map(function ($p){
                        return ['title' => $p->title, 'url' => $p->url];
                    },$replies);
                    Log::debug($replies);
                    $msg = new MultiMessageBuilder();
                    foreach ($replies as $reply)
                    {
                        $_msg = new TextMessageBuilder($reply);
                        $msg->add($_msg);
                    }

                    $response = $bot->replyMessage($request->events[0]['replyToken'], $msg);
                    DB::table('mobiles')->truncate();
                    DB::table('records')->truncate();

                }
                else if($status == 2){
                    if(strpos($text, '')){
                        $text = "賣";
                    }elseif(strpos($text, '賣')) {
                        $text = "買";
                    }else{
                        $text = "";
                    }
                    $reply = "請問您想要找什麼樣的手機? ex:iphone 6s";
                    $user = Mobile::where('userId', $user_id)->first();
                    $user->update(['text' => $text, 'status' => 3]);
                    Record::create([
                        'userId' => $user_id,
                        'key' => 'country',
                        'value' => $text,
                    ]);
                }
                else if($status == 1) {
                    if(strpos($text, '買')){
                        $text = "賣";
                    }elseif(strpos($text, '賣')) {
                        $text = "買";
                    }else{
                        $reply = "請問您想要購買/賣出手機?";
                        $reply = new TextMessageBuilder($reply);
                        $bot->replyMessage($request->events[0]['replyToken'], $reply);
                        return;
                    }
                    $reply = "請問您要搜尋的縣市為? ex:台北/台中/台南"."";
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
        } else{
            $reply = "請問您想要購買/賣出手機?";
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
    function test(Request $request){
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
            'title' => "title:".$request->title,
            'url' => "url:".$request->url,
            'country' => "country:".$request->country,
            'zone' => "zone:".$request->zone,
            'model' => "model:".$request->model,
            'type' =>"type:".$request->type,
            'date' => "date:".$request->date,
            'price' => "price:".$request->price,
        ];
        $reply = "請輸入欲查詢的地區";

        $msg = new MultiMessageBuilder();
        foreach ($replies as $reply)
        {
            $_msg = new TextMessageBuilder($reply);
            $msg->add($_msg);
        }


        $response = $bot->replyMessage($request->events[0]['replyToken'], $msg);
        if ($response->isSucceeded()) {
            Log::debug('Succeeded!');
            return;
        }
    }

}
