<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use App\Models\UserStage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Actions;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramController extends Controller{
    
    public function receiveUpdate(Request $request){
        $data = $request->all();
        $user_id = $data['message']['from']['id'];
        $chat_message = $data['message']['text'];

        try{
            $user = $this->manageUser($data);

            if(str_contains($chat_message, 'Author')){
                return $this->showAuthor($user_id);
            }

            if(str_contains($chat_message, '/repeat')){
                $this->repeatProccess($user->id);
            }
            
            if(str_contains($chat_message, 'Expense')){
                return $this->sendExpenseMessage($data, $user->id);
            }

            if(str_contains($chat_message, 'Income')){
                return $this->sendIncomeMessage($data, $user->id, $user_id);
            }

            if(str_contains($chat_message, 'Report')){
                return $this->sendReportMessage($data, $user->id, $user_id);
            }

            $check_user_stage = $this->checkUserStage($user->id);

            if($check_user_stage == 11 || $check_user_stage == 12){
                return $this->sendExpenseMessage($data, $user->id);
            }

            if($check_user_stage == 21){
                return $this->sendIncomeMessage($data, $user->id, $user_id);
            }

            return $this->sendWelcomeMessage($data, $user_id);
        }catch(Exception $e){
            Log::info($e->getMessage());
            $this->sendErrorMessage($data, $user_id);
        }

    }

    public function manageUser($data){
        $user = User::firstOrCreate(
            ['userid' =>  $data['message']['from']['id']],
            [
                'username' => $data['message']['from']['username'], 
                'first_name' => $data['message']['from']['first_name'], 
                'last_name' => $data['message']['from']['last_name']
            ]
        );

        UserStage::firstOrCreate(
            ['user_id' =>  $user->id]
        );

        return $user;

        // Log::info($user);
    }

    public function checkUserStage($user_id){
        $user_stage = UserStage::where('user_id', $user_id)->get();
        return $user_stage[0]->stage;
    }

    public function updateUserStage($user_id, $stage){
        $user_stage = UserStage::where('user_id', $user_id)->get();
        $user_stage[0]->stage = $stage;
        $user_stage[0]->update();
    }

    public function sendWelcomeMessage($data, $user_id){
        
        $user_id = $data['message']['from']['id'];

        $initial_text = 'Welcome '. $data['message']['from']['first_name']. ' '. $data['message']['from']['last_name'] .' 👏 to expense bot, This bot is made for those of you who want to record your personal finances via telegram.';

        // This will update the chat status to typing...
        Telegram::sendChatAction(['chat_id' => $user_id, 'action' => Actions::TYPING]);
    
        Telegram::sendMessage(['chat_id' => $user_id, 'text' => $initial_text]);

        $keyboard = [
            ['+ Expense 💸', '+ Income 💰'],
            ['Check Report 📊', 'Author 😎']
        ];

        $params = [
            'keyboard' => $keyboard, 
            'resize_keyboard' => true, 
            'one_time_keyboard' => true
        ];

        $reply_markup = Keyboard::make($params);
        
        
        Telegram::sendMessage([
            'chat_id' => $user_id, 
            'text' => 'Please choose options below', 
            'reply_markup' => $reply_markup
        ]);

    }

    public function sendErrorMessage($data, $user_id){
        $initial_text = 'Oppss! sorry 😥 '. $data['message']['from']['first_name']. ' '. $data['message']['from']['last_name'] .' looks like something went wrong here, please type /repeat 😔';
        Telegram::sendMessage(['chat_id' => $user_id, 'text' => $initial_text]);
    }

    public function sendExpenseMessage($data, $user_id, $expense = null){
        
        $check_user_stage = $this->checkUserStage($user_id);
        $telegram_user_id = $data['message']['from']['id'];

        if($check_user_stage == 1){
    
            Telegram::sendChatAction(['chat_id' => $telegram_user_id, 'action' => Actions::TYPING]);

            $initial_text = 'Okay '. $data['message']['from']['first_name']. ' '. $data['message']['from']['last_name'];
    
            Telegram::sendChatAction(['chat_id' => $telegram_user_id, 'action' => Actions::TYPING]);
        
            Telegram::sendMessage(['chat_id' => $telegram_user_id, 'text' => $initial_text]);
    
            $keyboard = [
                ['+ House 🏠', '+ Food 🍽️'],
                ['+ Transportation 🏎️', '+ Study 🎓'],
                ['+ Hobby 🎮', '+ Health 🤒'],
                ['+ Internet 📱', '+ Gone / Stolen 🤯'],
                ['+ Giving 💗']
            ];
    
            $params = [
                'keyboard' => $keyboard, 
                'resize_keyboard' => true, 
                'one_time_keyboard' => true
            ];
    
            $reply_markup = Keyboard::make($params);
            
            
            Telegram::sendMessage([
                'chat_id' => $telegram_user_id, 
                'text' => 'Please choose one of expense category below 👇', 
                'reply_markup' => $reply_markup
            ]);

            $this->updateUserStage($user_id, 11);

            $expense = ['user_id' => $user_id];
            return $this->storeExpense($expense, $user_id, 11);
        }

        if($check_user_stage == 11){
            $expense_data = [
                'category' => $data['message']['text']
            ];

            $this->storeExpense($expense_data, $user_id, 12);
            return $this->sendInputExpenseMessage($data, $user_id, $telegram_user_id);
        }

        if($check_user_stage == 12){
            $value = preg_replace('/[^0-9]/', '', $data['message']['text']);
            if(is_numeric($value)){
                $expense_data = [
                    'amount' => $data['message']['text']
                ];
                $expense = $this->storeExpense($expense_data, $user_id, 12);
                if($expense){
                    $initial_text = 'Awesome '. $data['message']['from']['first_name']. ' '. $data['message']['from']['last_name']. ' your expense saved successfully now to add new expense please type /repeat';
                    Telegram::sendChatAction(['chat_id' => $telegram_user_id, 'action' => Actions::TYPING]);
                    Telegram::sendMessage(['chat_id' => $telegram_user_id, 'text' => $initial_text]);

                    $this->updateUserStage($user_id, 1);
                }
            }else{
                $custom_message = 'Gezzz, please input a number not a text';
                return $this->sendInputExpenseMessage($data, $user_id, $telegram_user_id, $custom_message);
            }
            
        }
    }
    

    public function sendInputExpenseMessage($data, $user_id, $telegram_user_id, $custom_message = null){
        Telegram::sendChatAction(['chat_id' => $telegram_user_id, 'action' => Actions::TYPING]);

        $message = $custom_message ?? 'Great '. $data['message']['from']['first_name']. ' '. $data['message']['from']['last_name']. ' now please type amount of your expense 👇';
        Telegram::sendChatAction(['chat_id' => $telegram_user_id, 'action' => Actions::TYPING]);
    
        Telegram::sendMessage(['chat_id' => $telegram_user_id, 'text' => $message]);

        $this->updateUserStage($user_id, 12);
    }


    public function storeExpense($expense_data, $user_id, $user_stage){
        if($user_stage == 11){
            Expense::create($expense_data);
        }else{
            $expense = Expense::where('user_id', $user_id)->latest()->first();
            $expense->update($expense_data);

            return $expense;
        }
    }

    public function sendIncomeMessage($data, $user_id, $telegram_user_id, $custom_message = null){
        $check_user_stage = $this->checkUserStage($user_id);
        if($check_user_stage != 21){
            $this->sendInputIncomeMessage($data, $telegram_user_id);
    
            $this->updateUserStage($user_id, 21);
        }else{
            $value = preg_replace('/[^0-9]/', '', $data['message']['text']);
            if(is_numeric($value)){
                $income_data = [
                    'user_id' => $user_id,
                    'amount' => $data['message']['text']
                ];
                $income = Income::create($income_data);
                if($income){
                    $initial_text = 'Awesome '. $data['message']['from']['first_name']. ' '. $data['message']['from']['last_name']. ' your income saved successfully now to add new income please type /repeat';
                    Telegram::sendChatAction(['chat_id' => $telegram_user_id, 'action' => Actions::TYPING]);
                    Telegram::sendMessage(['chat_id' => $telegram_user_id, 'text' => $initial_text]);

                    $this->updateUserStage($user_id, 1);
                }
            }else{
                $custom_message = 'Gezzz 👿, please input a number not a text';
                return $this->sendInputIncomeMessage($data,$telegram_user_id, $custom_message);
            }  
        }
    }

    public function sendInputIncomeMessage($data, $telegram_user_id, $custom_message = null){
        Telegram::sendChatAction(['chat_id' => $telegram_user_id, 'action' => Actions::TYPING]);
    
        $message = $custom_message ?? 'Great '. $data['message']['from']['first_name']. ' '. $data['message']['from']['last_name']. ' now please type amount of your income 👇';
        Telegram::sendChatAction(['chat_id' => $telegram_user_id, 'action' => Actions::TYPING]);
    
        Telegram::sendMessage(['chat_id' => $telegram_user_id, 'text' => $message]);
    }

    public function sendReportMessage($data, $user_id, $telegram_user_id){
        $income_sum = Income::where('user_id', $user_id)->sum('amount');
        $expense_sum = Expense::where('user_id', $user_id)->sum('amount');

        $balance = $income_sum - $expense_sum;
        $balance_text = $expense_sum >= $income_sum ? 'Balance 😰 : ' : 'Balance 🤑 : ' ;

        $initial_text = 'Hello '. $data['message']['from']['first_name']. ' '. $data['message']['from']['last_name']. ' here is your report 📊'. PHP_EOL .'Income 💰 : '. $income_sum .' '. PHP_EOL .'Expense 💸 : '. $expense_sum .' '. PHP_EOL . $balance_text .''. $balance .' ';
        Telegram::sendChatAction(['chat_id' => $telegram_user_id, 'action' => Actions::TYPING]);
        Telegram::sendMessage(['chat_id' => $telegram_user_id, 'text' => $initial_text]);
    }

    public function repeatProccess($user_id){
        $user_stage = UserStage::where('user_id', $user_id)->get();
        $user_stage[0]->stage = 1;
        $user_stage[0]->update();
    }

    public function showAuthor($telegram_user_id){
        $initial_text = 'https://github.com/mertayasa';
        Telegram::sendChatAction(['chat_id' => $telegram_user_id, 'action' => Actions::TYPING]);
        Telegram::sendMessage(['chat_id' => $telegram_user_id, 'text' => $initial_text]);
    }

}
