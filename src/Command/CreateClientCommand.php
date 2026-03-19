<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:client:create',
    description: 'Creates a new API client with a client_id and client_secret',
)]
class CreateClientCommand extends Command
{
    private const SCOPES = ['article:read', 'article:write'];

    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $clientId = Uuid::v4()->toRfc4122();
        $plainSecret = bin2hex(random_bytes(32));

        $client = new Client();
        $client->setClientId($clientId);
        $client->setClientSecret($this->passwordHasher->hashPassword($client, $plainSecret));
        $client->setScopes(self::SCOPES);

        $this->clientRepository->save($client);

        $io->success('API client created successfully.');
        $io->table(
            ['Field', 'Value'],
            [
                ['client_id', $clientId],
                ['client_secret', $plainSecret],
                ['scopes', implode(', ', self::SCOPES)],
            ]
        );
        $io->warning('Store the client_secret securely. It will not be shown again.');

        return Command::SUCCESS;
    }
}
