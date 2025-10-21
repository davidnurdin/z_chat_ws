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
    name: 'bot',
    description: 'Add a short description for your command',
)]
class BotCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    private function generateHumanLogin(): array
    {
        $path = __DIR__ . '/nickFr.txt';
        $gender = 'X';
        $login = 'guest';

        if (is_readable($path)) {
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines) && count($lines) > 0) {
                $line = $lines[array_rand($lines)];
                $parts = explode(',', $line);
                $rawNick = trim($parts[0] ?? '');
                $numGender = trim($parts[1] ?? '');

                // Map numeric gender: 1 = femme (F), 2 = homme (H)
                if (mt_rand(1, 100) <= 18) {
                    // De temps en temps on met X
                    $gender = 'X';
                } else {
                    if ($numGender === '1') { $gender = 'F'; }
                    elseif ($numGender === '2') { $gender = 'H'; }
                    else { $gender = 'X'; }
                }

                // Replace spaces randomly: '_' or '-' or remove
                $spaceMode = mt_rand(1, 3);
                if ($spaceMode === 1) {
                    $nick = str_replace(' ', '_', $rawNick);
                } elseif ($spaceMode === 2) {
                    $nick = str_replace(' ', '-', $rawNick);
                } else {
                    $nick = str_replace(' ', '', $rawNick);
                }

                // Sometimes add one to three 'X' in the nickname at random positions
                if (mt_rand(1, 100) <= 22) {
                    $countX = mt_rand(1, 3);
                    for ($i = 0; $i < $countX; $i++) {
                        $pos = mt_rand(0, max(0, strlen($nick)));
                        $nick = substr($nick, 0, $pos) . 'X' . substr($nick, $pos);
                    }
                }

                // Basic sanitization: remove commas and newlines to avoid protocol conflicts
                $nick = str_replace([",", "\r", "\n"], '', $nick);
                $nick = trim($nick);
                if ($nick !== '') {
                    $login = $nick;
                }
            }
        }

        return ['login' => $login, 'gender' => $gender];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connectionId = uniqid('bot');

        // Generate a more human-like, French-style login with gender (H/F/X)
        $identity = $this->generateHumanLogin();
        $login = $identity['login'];
        $gender = $identity['gender'] ?? 'X';

        $room = 'general' ;

        auth($connectionId,$login,$gender);
        enterRoom($room,$connectionId,$login,$gender);

//        sendToRoom($room, $msg, '#444444', $login, $connectionId);

        return Command::SUCCESS;
    }
}
