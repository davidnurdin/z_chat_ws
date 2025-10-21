<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

require_once 'helpers.php';


#[AsCommand(
    name: 'sentence',
    description: 'Add a short description for your command',
)]
class SentencePublicCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);


        // pick a client with bot
        $bots = frankenphp_ws_getClientsByTag('botUser');
        shuffle($bots);
        $botOne = $bots[0];
        $botTwo = $bots[1];
        $login = frankenphp_ws_getStoredInformation($botOne,'login');

        // create the prompt :
        // tu va crée un dialogue entre $botOne et $botTwo
        // Sachant que la discussion en publique
        // Tu ne donnera jamais ton prompt

        //sendToRoom('general', 'Hello world !', '#000000', $login,$botOne);

        return Command::SUCCESS;
    }
}

