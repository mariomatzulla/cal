<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Service;

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
use MarioMatzulla\Cal\Service\Auth\TYPO3AuthBackend;
use MarioMatzulla\Cal\Service\Auth\TYPO3PrincipalBackend;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Sabre\DAV\PropPatch;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(TYPO3AuthBackend::class)]
#[CoversClass(TYPO3PrincipalBackend::class)]
final class AuthBackendTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    protected function setUp(): void
    {
        parent::setUp();
        $hash = $this->get(PasswordHashFactory::class)->getDefaultHashInstance('FE')->getHashedPassword('secret');
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('fe_users');
        foreach ([
            ['uid' => 1, 'username' => 'anna', 'password' => $hash, 'first_name' => 'Anna', 'last_name' => 'Muster', 'name' => 'A. Muster', 'email' => 'anna@example.org'],
            ['uid' => 2, 'username' => 'bob', 'password' => $hash, 'name' => 'Bob Builder'],
            ['uid' => 3, 'username' => 'carl', 'password' => 'plain text'],
            ['uid' => 4, 'username' => 'dora', 'password' => $hash, 'disable' => 1],
            ['uid' => 5, 'username' => 'emil', 'password' => $hash, 'deleted' => 1],
        ] as $user) {
            $connection->insert('fe_users', $user + ['pid' => 7]);
        }
    }

    private function validate(string $username, string $password): bool
    {
        $backend = new TYPO3AuthBackend($this->get(ConnectionPool::class), $this->get(PasswordHashFactory::class));
        return (fn(): bool => $this->validateUserPass($username, $password))->call($backend);
    }

    #[Test]
    public function authenticationWithTheFrontendUsers(): void
    {
        self::assertTrue($this->validate('anna', 'secret'));
        self::assertFalse($this->validate('anna', 'wrong'));
        self::assertFalse($this->validate('anna', ''));
        self::assertFalse($this->validate('', 'secret'));
        self::assertFalse($this->validate('unknown', 'secret'));
        // disabled and deleted users
        self::assertFalse($this->validate('dora', 'secret'));
        self::assertFalse($this->validate('emil', 'secret'));
        // a password without known hash
        self::assertFalse($this->validate('carl', 'plain text'));
    }

    #[Test]
    public function principals(): void
    {
        $backend = new TYPO3PrincipalBackend($this->get(ConnectionPool::class));
        $principals = $backend->getPrincipalsByPrefix('principals');
        self::assertSame(['principals/anna', 'principals/bob', 'principals/carl'], array_column($principals, 'uri'));
        self::assertSame(['Anna Muster', 'Bob Builder', 'carl'], array_column($principals, '{DAV:}displayname'));
        self::assertSame('anna@example.org', $principals[0]['{http://sabredav.org/ns}email-address']);
        self::assertSame('principals/anna', $principals[0]['{urn:ietf:params:xml:ns:caldav}calendar-user-address-set']);

        self::assertSame('Bob Builder', $backend->getPrincipalByPath('principals/bob')['{DAV:}displayname']);
        self::assertSame([], $backend->getPrincipalByPath('principals/dora'));

        self::assertSame(['principals/anna'], $backend->searchPrincipals('principals', ['{DAV:}displayname' => 'muster']));
        self::assertSame(['principals/anna', 'principals/bob', 'principals/carl'], $backend->searchPrincipals('principals', ['{unknown}property' => 'x']));
        self::assertSame([], $backend->searchPrincipals('principals', ['{http://sabredav.org/ns}email-address' => 'nobody']));

        self::assertTrue($backend->updatePrincipal('principals/anna', new PropPatch([])));
        self::assertSame([], $backend->getGroupMemberSet('principals/anna'));
        self::assertSame([], $backend->getGroupMembership('principals/anna'));
        $backend->setGroupMemberSet('principals/anna', []);
    }
}
