<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class VerificarGeracao extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verificar_geracao';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar a quantidade de energia gerada por gerador';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function status(string $status)
    {
        $_status = [
            '1' => 'Normal',
            '-1' => 'Desconectado',
            '3' => 'Falha',
            '4' => 'Desligado',
        ];

        return $_status[$status] ?? 'Desconhecido';
    }

    public function handle()
    {

        $telegramChatIds = config('telegram.chat_ids');

        $client = new Client(array(
            'cookies' => true
        ));

        $response = $client->request('POST', 'http://solar-monitoramento.intelbras.com.br/login', [
            'timeout' => 30,
            'form_params' => [
                'account' => config('intelbras.user'),
                'password' => config('intelbras.password'),
                'validateCode' => '',
                'lang' => 'en'
            ]
        ]);

        // Geradores
        $response = $client->request('POST', 'http://solar-monitoramento.intelbras.com.br/panel/getDevicesByPlantList', [
            'form_params' => [
                'plantId' => config('intelbras.plant_id'),
                'currPage' => '1',
            ]
        ]);

        $retorno = json_decode($response->getBody(), true);

        if (isset($retorno['result']) && $retorno['result'] == '1') {

            $total = floatval($retorno['obj']['datas'][0]['eToday']) + floatval($retorno['obj']['datas'][1]['eToday']) + floatval($retorno['obj']['datas'][2]['eToday']);

            $message = "⚡*Gerador 1:*\n" .
                "Energia gerada: {$retorno['obj']['datas'][0]['eToday']}kWh\n" .
                "Potência atual: {$retorno['obj']['datas'][0]['pac']}W\n" .
                "Status: {$this->status($retorno['obj']['datas'][0]['status'])}\n\n" .
                "⚡*Gerador 2:*\n" .
                "Energia gerada: {$retorno['obj']['datas'][1]['eToday']}kWh\n" .
                "Potência atual: {$retorno['obj']['datas'][1]['pac']}W\n" .
                "Status: {$this->status($retorno['obj']['datas'][1]['status'])}\n\n" .
                "⚡*Gerador 3:*\n" .
                "Energia gerada: {$retorno['obj']['datas'][2]['eToday']}kWh\n" .
                "Potência atual: {$retorno['obj']['datas'][2]['pac']}W\n" .
                "Status: {$this->status($retorno['obj']['datas'][2]['status'])}\n\n\n" .
                "🔋*Total:* {$total}kWh";

            foreach ($telegramChatIds as $chatId) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                ]);
            }

            $this->info(str_replace('*', ' ', $message));
        } else {
            $this->error('Erro ao verificar a energia gerada');
        }
    }
}
