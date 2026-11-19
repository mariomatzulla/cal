<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service\Auth;

use Sabre\DAV\Auth\Backend\AbstractBasic;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;

class TYPO3AuthBackend extends AbstractBasic
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly PasswordHashFactory $passwordHashFactory,
    ) {}

    /**
     * Überprüft Benutzername und Passwort gegen die TYPO3 fe_users Tabelle
     */
    protected function validateUserPass($username, $password): bool
    {
        if (empty($username) || empty($password)) {
            return false;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('fe_users');

        // Benutzer aus der DB laden
        $user = $queryBuilder
        ->select('uid', 'username', 'password')
        ->from('fe_users')
        ->where(
            $queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter($username)),
            $queryBuilder->expr()->eq('disable', 0),
            $queryBuilder->expr()->eq('deleted', 0),
        )
            ->executeQuery()
            ->fetchAssociative();

        if (!$user || !isset($user['password'])) {
            return false;
        }

        // Passwort mittels TYPO3 PasswordHashFactory validieren
        try {
            $hashInstance = $this->passwordHashFactory->get((string)$user['password'], 'FE');

            return $hashInstance->checkPassword((string)$password, (string)$user['password']);
        } catch (\Exception $e) {
            // Falls der Hash-Algorithmus ungültig ist oder ein Fehler auftritt
            return false;
        }
    }
}
