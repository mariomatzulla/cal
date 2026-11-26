<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Upgrades;

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
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Upgrades\ChattyInterface;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * Creates the URL segments (field "slug") of events, locations and organizers, which are
 * needed for the speaking URLs of the route enhancer (Configuration/Routes/Cal.yaml).
 */
#[UpgradeWizard('cal_slugs')]
final class SlugUpgradeWizard implements UpgradeWizardInterface, ChattyInterface
{
    private ?OutputInterface $output = null;

    public function __construct(
        private readonly SlugService $slugService,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Calendar Base (cal): create the URL segments of events, locations and organizers, make them unique';
    }

    public function getDescription(): string
    {
        return 'Fills the empty field "URL segment" (slug) of events, locations and organizers and replaces URL segments '
            . 'used by an older record of the same language (e.g. in a folder outside of a site). The speaking URLs '
            . 'of the detail views use it. Run the wizard "migrate calendars, events, locations and organizers" first '
            . 'when updating from cal 1.x.';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        foreach (SlugService::TABLES as $table) {
            if ($this->slugService->countMissing($table) > 0) {
                return true;
            }
        }
        return false;
    }

    public function executeUpdate(): bool
    {
        foreach (SlugService::TABLES as $table) {
            $count = $this->slugService->fillMissing($table);
            $this->output?->writeln(sprintf('%s: %d URL segments created or made unique', $table, $count));
        }
        return true;
    }
}
