<?php

namespace App\Http\Controllers;

use App\Mobile;
use App\Product;
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


        if(count($status = Mobile::where('user_id', $user_id)->get()) > 0 || count(Mobile::where('user_id', $user_id)->get()) > 0 || strpos($text, '手機')!==false){
            if(count($status = Mobile::where('user_id', $user_id)->get()) > 0){
                $status = Mobile::where('user_id', $user_id)->first()->status;
                if($status == 4){
                    $reply = "查詢結果:";
                }
                else if($status == 3){
                    $reply = "請問您要搜尋的手機廠牌為:apple/asus/sony/三星/oppo/小米/華為?";
                }
                else if($status == 2){
                    $reply = "請問您要搜尋的縣市為："."";
                }
                else if($status == 1){
                    $reply = "請問您要搜尋的地區:北部/中部/南部/東部?"."";
                    $user = Mobile::where('user_id', $user_id)->latest()->first();
                }
            }else{
                $reply = "請問您想要購買/賣出手機?";
                $status = 1;
                Mobile::create([
                    'user_id' => $user_id,
                    'text' => $text,
                    'status' => $status,
                ]);
            }
        } else{
                $reply = "您好，請問我能為您提供什麼服務?";
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
