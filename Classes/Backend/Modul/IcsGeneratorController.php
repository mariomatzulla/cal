<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Backend\Modul;

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 extension Calendar Base (cal) project - inspiring people to share!
 */

use MarioMatzulla\Cal\Service\IcsGenerator;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Backend module "Cal ICS Generator": (re)generates the ICS data of events.
 */
class IcsGeneratorController extends ActionController
{
    private const LLL = 'LLL:EXT:cal/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private readonly IcsGenerator $icsGenerator,
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
    ) {}

    public function indexAction(): ResponseInterface
    {
        // Erstellt das TYPO3 Backend-Rahmentemplate
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        // Titel im DocHeader setzen
        $moduleTemplate->setTitle($this->translate('backend_module_ics_generator_header'));

        // Rendering des Views Resources/Private/Templates/Admin/Ics/Index.html
        return $moduleTemplate->renderResponse('Admin/Ics/Index');
    }

    public function generateAction(): ResponseInterface
    {
        $this->icsGenerator->generate($this->request);
        $this->addFlashMessage(
            $this->translate('backend_module_ics_generator_successMessage'),
            $this->translate('backend_module_ics_generator_successTitle'),
            ContextualFeedbackSeverity::OK,
        );
        // Erstellt das TYPO3 Backend-Rahmentemplate
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        // Titel im DocHeader setzen
        $moduleTemplate->setTitle($this->translate('backend_module_ics_generator_header'));

        // Rendering des Views Resources/Private/Templates/Admin/Ics/Generate.html
        return $moduleTemplate->renderResponse('Admin/Ics/Generate');
    }

    public function checkAction(): ResponseInterface
    {
        // Erstellt das TYPO3 Backend-Rahmentemplate
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        // Titel im DocHeader setzen
        $moduleTemplate->setTitle($this->translate('backend_module_ics_generator_header'));

        // Daten an die Fluid-View übergeben
        $moduleTemplate->assignMultiple([
            'result' => $this->icsGenerator->checkWithReasons(),
        ]);

        // Rendering des Views Resources/Private/Templates/Admin/Ics/Check.html
        return $moduleTemplate->renderResponse('Admin/Ics/Check');
    }

    private function translate(string $key): string
    {
        return (string)(LocalizationUtility::translate(self::LLL . $key) ?? $key);
    }
}
