<?php declare(strict_types=1);

namespace Gos\Bundle\WebSocketBundle\Client;

use Gos\Bundle\WebSocketBundle\Client\Auth\WebsocketAuthenticationProviderInterface;
use Gos\Bundle\WebSocketBundle\Client\Exception\ClientNotFoundException;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\Topic;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class ClientManipulator implements ClientManipulatorInterface
{
    private ClientStorageInterface $clientStorage;
    private WebsocketAuthenticationProviderInterface $authenticationProvider;

    public function __construct(
        ClientStorageInterface $clientStorage,
        WebsocketAuthenticationProviderInterface $authenticationProvider
    ) {
        $this->clientStorage = $clientStorage;
        $this->authenticationProvider = $authenticationProvider;
    }

    /**
     * @return ClientConnection[]
     */
    public function findAllByUsername(Topic $topic, string $username): array
    {
        $result = [];

        /** @var ConnectionInterface $connection */
        foreach ($topic as $connection) {
            $client = $this->getClient($connection);

            if ($client instanceof AnonymousToken) {
                continue;
            }

            $clientUsername = method_exists($client, 'getUserIdentifier') ? $client->getUserIdentifier() : $client->getUsername();

            if ($clientUsername === $username) {
                $result[] = new ClientConnection($client, $connection);
            }
        }

        return $result;
    }

    /**
     * @return ClientConnection[]
     */
    public function findByRoles(Topic $topic, array $roles): array
    {
        $result = [];

        /** @var ConnectionInterface $connection */
        foreach ($topic as $connection) {
            $client = $this->getClient($connection);

            if ($client instanceof AnonymousToken) {
                continue;
            }

            foreach ($client->getRoleNames() as $role) {
                if (\in_array($role, $roles)) {
                    $result[] = new ClientConnection($client, $connection);

                    continue 2;
                }
            }
        }

        return $result;
    }

    /**
     * @return ClientConnection[]
     */
    public function getAll(Topic $topic, bool $anonymous = false): array
    {
        $result = [];

        /** @var ConnectionInterface $connection */
        foreach ($topic as $connection) {
            $client = $this->getClient($connection);

            if (true !== $anonymous && $client instanceof AnonymousToken) {
                continue;
            }

            $result[] = new ClientConnection($client, $connection);
        }

        return $result;
    }

    public function getClient(ConnectionInterface $connection): TokenInterface
    {
        $storageId = $this->clientStorage->getStorageId($connection);

        try {
            return $this->clientStorage->getClient($storageId);
        } catch (ClientNotFoundException $e) {
            // User is gone due to ttl
            $this->authenticationProvider->authenticate($connection);

            return $this->getClient($connection);
        }
    }

    public function getUser(ConnectionInterface $connection): string | \Stringable | UserInterface
    {
        return $this->getClient($connection)->getUser();
    }
}
