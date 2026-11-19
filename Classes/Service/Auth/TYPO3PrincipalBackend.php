<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service\Auth;

use Sabre\DAV\PropPatch;
use Sabre\DAVACL\PrincipalBackend\AbstractBackend;
use TYPO3\CMS\Core\Database\ConnectionPool;

class TYPO3PrincipalBackend extends AbstractBackend
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Gibt alle aktiven fe_users als Principals zurück
     */
    public function getPrincipalsByPrefix($prefixPath)
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('fe_users');

        $users = $queryBuilder
        ->select('uid', 'username', 'email', 'name', 'first_name', 'last_name')
        ->from('fe_users')
        ->where(
            $queryBuilder->expr()->eq('disable', 0),
            $queryBuilder->expr()->eq('deleted', 0),
        )
            ->executeQuery()
            ->fetchAllAssociative();

        $principals = [];
        foreach ($users as $user) {
            $username = $user['username'];
            $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            if ($displayName === '') {
                $displayName = $user['name'] ?: $username;
            }

            $principals[] = [
                'uri' => 'principals/' . $username,
                '{DAV:}displayname' => $displayName,
                '{http://sabredav.org/ns}email-address' => $user['email'] ?? '',
                '{urn:ietf:params:xml:ns:caldav}calendar-user-address-set' => 'principals/' . $username,
            ];
        }

        return $principals;
    }

    public function getPrincipalByPath($path)
    {
        $principals = $this->getPrincipalsByPrefix('principals');
        foreach ($principals as $principal) {
            if ($principal['uri'] === $path) {
                return $principal;
            }
        }
        return null;
    }

    public function updatePrincipal($path, PropPatch $propPatch)
    {
        // Da wir die Benutzerdaten über TYPO3 (Backend/fe_users) pflegen
        // und nicht über CalDAV-Clients überschreiben wollen, geben wir hier einfach true zurück.
        return true;
    }

    public function searchPrincipals($prefixPath, array $searchCriteria, $ast = null)
    {
        $result = [];
        $principals = $this->getPrincipalsByPrefix($prefixPath);

        foreach ($principals as $principal) {
            $match = true;
            foreach ($searchCriteria as $key => $value) {
                if (isset($principal[$key]) && stripos((string)$principal[$key], (string)$value) === false) {
                    $match = false;
                }
            }
            if ($match) {
                $result[] = $principal['uri'];
            }
        }
        return $result;
    }

    public function getGroupMemberSet($principal)
    {
        return [];
    }

    public function setGroupMemberSet($principal, array $members) {}

    public function getGroupMembership($principal)
    {
        return [];
    }
}
