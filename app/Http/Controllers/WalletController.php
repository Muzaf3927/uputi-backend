<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    // Получить кошелек и баланс
    public function getWallet()
    {
        $wallet = Auth::user()->wallet;

        if (!$wallet) {
            // если нет — создаем новый
            $wallet = Wallet::create(['user_id' => Auth::id()]);
        }

        return response()->json([
            'balance' => $wallet->balance
        ]);
    }

    // Пополнение баланса
    public function deposit(Request $request)
    {

        $request->validate([
            'amount' => 'required|integer',
            'description' => 'nullable|string'
        ]);

        $wallet = Auth::user()->wallet;

        if (!$wallet) {
            $wallet = Wallet::create(['user_id' => Auth::id()]);
        }

        $wallet->balance += $request->amount;
        $wallet->save();

        Transaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => $request->amount,
            'description' => $request->description ?? 'Пополнение баланса'
        ]);


        return response()->json([
            'message' => 'Баланс успешно пополнен',
            'balance' => $wallet->balance
        ]);
    }

    // История транзакций
    public function getTransactions()
    {
        $wallet = Auth::user()->wallet;

        if (!$wallet) {
            return response()->json(['transactions' => []]);
        }

        $transactions = $wallet->transactions()->latest()->paginate(10);

        return response()->json([
            'transactions' => $transactions
        ]);
    }

    // Внутренний метод для вычета комиссии или выплат
    public static function processTransaction(Wallet $wallet, float $amount, string $type, string $description = null)
    {
        // проверка на достаточный баланс
        if ($wallet->balance < $amount) {
            throw new \Exception('Недостаточно средств в кошельке');
        }

        $wallet->balance -= $amount;
        $wallet->save();

        return Transaction::create([
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'description' => $description
        ]);
    }
}
