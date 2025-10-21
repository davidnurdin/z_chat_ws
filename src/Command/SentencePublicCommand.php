<?php

namespace App\Command;

use App\Service\ChatHelpers;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;


// ./runCommandInDev.sh ./websocket php-cli bin/console --env=dev bot (2x)
// ./runCommandInDev.sh ./websocket php-cli bin/console --env=dev sentence


#[AsCommand(
    name: 'sentence',
    description: 'Add a short description for your command',
)]
class SentencePublicCommand extends Command
{
    public function __construct(private ParameterBagInterface $params)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $apiKey = $this->params->get('ai.api_key');

        // pick a client with bot
        $bots = frankenphp_ws_getClientsByTag('botUser');
        shuffle($bots);
        $botOne = $bots[0];
        $botTwo = $bots[1];
        $loginOne = frankenphp_ws_getStoredInformation($botOne,'login');
        $loginTwo = frankenphp_ws_getStoredInformation($botTwo,'login');



        $platform = PlatformFactory::create($apiKey);
        $model = 'gemini-2.0-flash';

        $prompt = <<<PROMPT
Voici deux pseudo : {$loginOne} avec le clientID : {$botOne} et {$loginTwo} avec le clientID : {$botTwo}.
Tu imagine une conversation sur un chatRoom entre ces deux personnes.
Je veux 1 ligne par pseudo max.
Je veux 10 phrases pour chacun.
Met une phrase par ligne.
Utilise un format json de renvois stp. Que je puisse itéré dessus. Ca dois etre un tableau de phrases, et dedans on aura "fromClientId","fromClientNick" et "sentence"
Si dans la phrase tu t'adresse a l'autre , tu fait sous ce format "pseudo > phrase"
Parle vraiment comme un humain. Voir desfois parle un peu jeune.
Aborde ces sujets : la politique , les rencontres , l'ennuie , la vie quotidienne, le travail, les loisirs.
Ne parle pas de toi meme en tant que modele de langage.
Evite les majuscule et la ponctiation trop formelle.
Tu as droit de faire des fautes.
PROMPT;

        $messages = new MessageBag(
            Message::forSystem($prompt),
            Message::ofUser('commence la conversation.'),
        );

        try {
            $result = $platform->invoke($model, $messages);
            $result = $result->asText() ;
        }
        catch (\Throwable $e) {
            dd($e);
        }



        $result = str_replace('```json','',$result);
        $result = str_replace('```','',$result);

        dump($result);
        $content = json_decode($result, true);
        foreach ($content as $item) {
            $fromClientId = $item['fromClientId'];
            $fromClientNick = $item['fromClientNick'];
            $sentence = $item['sentence'];
            ChatHelpers::sendToRoom('general', $sentence, '#000000', $fromClientNick,$fromClientId);
            sleep(rand(3,25));
        }
        //ChatHelpers::sendToRoom('general', $resultFirstBot, '#000000', $login,$botOne);




        return Command::SUCCESS;
    }
}

