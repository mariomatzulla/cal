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
use MarioMatzulla\Cal\Service\SlugService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(SlugService::class)]
final class SlugServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    private const TABLE = 'tx_cal_domain_model_event';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Slugs.csv');
    }

    /**
     * @return array<int, string> uid => slug
     */
    private function slugs(): array
    {
        $rows = $this->get(ConnectionPool::class)->getConnectionForTable(self::TABLE)->select(['uid', 'slug'], self::TABLE, ['deleted' => 0])->fetchAllAssociative();
        return array_column($rows, 'slug', 'uid');
    }

    #[Test]
    public function fillMissingCreatesUniqueSlugs(): void
    {
        $subject = new SlugService($this->get(ConnectionPool::class));
        self::assertTrue($subject->hasSlugField(self::TABLE));
        // empty slugs and the duplicate of record 4 (record 8 is a translation)
        self::assertSame(5, $subject->countMissing(self::TABLE));

        // only the events of calendar 2
        self::assertSame(1, $subject->fillMissing(self::TABLE, 2));
        self::assertSame('other-event', $this->slugs()[3]);

        self::assertSame(4, $subject->fillMissing(self::TABLE));
        $slugs = $this->slugs();
        self::assertSame('meeting', $slugs[1]);
        self::assertSame('meeting-1', $slugs[2]);
        // the older record keeps the slug, the duplicate gets a new one, the translation keeps it
        self::assertSame('existing', $slugs[4]);
        self::assertSame('existing-1', $slugs[7]);
        self::assertSame('existing', $slugs[8]);
        // a record without title gets the slug "default-<hash>" of the SlugHelper
        self::assertStringStartsWith('default-', $slugs[6]);
        self::assertSame(0, $subject->countMissing(self::TABLE));
    }

    #[Test]
    public function otherUniqueEvaluations(): void
    {
        // TYPO3 does not check records outside of a site with uniqueInSite: the duplicate stays a duplicate
        $GLOBALS['TCA'][self::TABLE]['columns']['slug']['config']['eval'] = 'uniqueInSite';
        $subject = new SlugService($this->get(ConnectionPool::class));
        $subject->fillMissing(self::TABLE, 1);
        self::assertSame(['meeting', 'meeting'], [$this->slugs()[1], $this->slugs()[2]]);
        // the duplicates 2 and 7, the empty slug of calendar 2
        self::assertSame(3, $subject->countMissing(self::TABLE));

        $GLOBALS['TCA'][self::TABLE]['columns']['slug']['config']['eval'] = 'uniqueInPid';
        (new SlugService($this->get(ConnectionPool::class)))->fillMissing(self::TABLE, 1);
        self::assertSame('meeting-1', $this->slugs()[2]);

        $GLOBALS['TCA'][self::TABLE]['columns']['slug']['config']['eval'] = 'unique';
        (new SlugService($this->get(ConnectionPool::class)))->fillMissing(self::TABLE, 2);
        self::assertSame('other-event', $this->slugs()[3]);
    }

    #[Test]
    public function slugsWithoutUniqueEvaluation(): void
    {
        unset($GLOBALS['TCA'][self::TABLE]['columns']['slug']['config']['eval']);
        (new SlugService($this->get(ConnectionPool::class)))->fillMissing(self::TABLE, 1);
        $slugs = $this->slugs();
        self::assertSame(['meeting', 'meeting'], [$slugs[1], $slugs[2]]);
    }

    #[Test]
    public function tablesWithoutSlugField(): void
    {
        $subject = new SlugService($this->get(ConnectionPool::class));
        self::assertFalse($subject->hasSlugField('tx_cal_domain_model_calendar'));
        self::assertSame(0, $subject->countMissing('tx_cal_domain_model_calendar'));
        self::assertSame(0, $subject->fillMissing('tx_cal_domain_model_calendar'));
    }
}
