<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Backend\Form;

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
use MarioMatzulla\Cal\Backend\Form\TempEventReadOnlyDataProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\FormDataProvider\EvaluateDisplayConditions;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(TempEventReadOnlyDataProvider::class)]
final class TempEventReadOnlyDataProviderTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private FlashMessageService $flashMessageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flashMessageService = new FlashMessageService();
        GeneralUtility::setSingletonInstance(FlashMessageService::class, $this->flashMessageService);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LANG']);
        parent::tearDown();
    }

    private static function formResult(string $table = 'tx_cal_domain_model_event', string $command = 'edit', int $isTemp = 1): array
    {
        return [
            'tableName' => $table,
            'command' => $command,
            'databaseRow' => ['isTemp' => $isTemp],
            'processedTca' => ['columns' => ['title' => ['config' => ['type' => 'input']], 'description' => ['config' => []]]],
        ];
    }

    #[Test]
    public function register(): void
    {
        TempEventReadOnlyDataProvider::register();
        self::assertSame(
            ['depends' => [EvaluateDisplayConditions::class]],
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][TempEventReadOnlyDataProvider::class],
        );
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][TempEventReadOnlyDataProvider::class]);
    }

    #[Test]
    public function importedEventsAreReadOnly(): void
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('sL')->willReturn('Importiert');
        $GLOBALS['LANG'] = $languageService;
        $result = (new TempEventReadOnlyDataProvider())->addData(self::formResult());
        self::assertTrue($result['processedTca']['columns']['title']['config']['readOnly']);
        self::assertTrue($result['processedTca']['columns']['description']['config']['readOnly']);
        $messages = $this->flashMessageService->getMessageQueueByIdentifier()->getAllMessages();
        self::assertCount(1, $messages);
        self::assertSame('Importiert', $messages[0]->getMessage());
    }

    #[Test]
    public function messageWithoutLanguageService(): void
    {
        (new TempEventReadOnlyDataProvider())->addData(self::formResult());
        self::assertSame('This event was imported from an external calendar and cannot be changed.', $this->flashMessageService->getMessageQueueByIdentifier()->getAllMessages()[0]->getMessage());
    }

    #[Test]
    public function otherRecordsAreNotChanged(): void
    {
        $provider = new TempEventReadOnlyDataProvider();
        foreach ([self::formResult('pages'), self::formResult('tx_cal_domain_model_event', 'new'), self::formResult('tx_cal_domain_model_event', 'edit', 0)] as $result) {
            self::assertSame($result, $provider->addData($result));
        }
        self::assertSame([], $this->flashMessageService->getMessageQueueByIdentifier()->getAllMessages());
    }
}
