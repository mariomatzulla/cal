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
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Utility\RecurrenceGenerator;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Backend module "Cal Indexer": rebuilds the index of recurring events.
 */
class IndexController extends ActionController
{
    private const LLL = 'LLL:EXT:cal/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly EventRepository $eventRepository,
        private readonly PageRepository $pageRepository,
        private readonly RecurrenceGenerator $recurrenceGenerator,
    ) {}

    public function indexAction(): ResponseInterface
    {
        return $this->createModuleTemplate()->renderResponse('Admin/Index/Index');
    }

    /**
     * @param list<int|string> $pageIds
     */
    public function resultAction(array $pageIds = [], string $starttime = '', string $endtime = ''): ResponseInterface
    {
        $start = $this->normalizeDate($starttime);
        $end = $this->normalizeDate($endtime);
        $pageIds = array_values(array_filter(array_map('intval', $pageIds), static fn(int $pid): bool => $pid > 0));

        if ($pageIds === [] || $start === null || $end === null) {
            $this->addFlashMessage(
                $this->translate('backend_module_indexer_missingFields'),
                $this->translate('backend_module_indexer_errorTitle'),
                ContextualFeedbackSeverity::WARNING,
            );
            return $this->redirect('generate');
        }

        $this->recurrenceGenerator->setRange($start, $end);
        $results = [];
        foreach ($pageIds as $pid) {
            $this->recurrenceGenerator->cleanIndexTable($pid);
            $page = $this->pageRepository->getPage($pid);
            $events = $this->recurrenceGenerator->generateIndex($pid);
            $results[] = [
                'pid' => $pid,
                'title' => (string)($page['title'] ?? ''),
                'count' => count($events),
                'events' => $events,
            ];
        }

        $this->addFlashMessage(
            $this->translate('backend_module_indexer_successMessage'),
            $this->translate('backend_module_indexer_successTitle'),
            ContextualFeedbackSeverity::OK,
        );

        $moduleTemplate = $this->createModuleTemplate();
        $moduleTemplate->assignMultiple([
            'results' => $results,
            'starttime' => $start,
            'endtime' => $end,
        ]);
        return $moduleTemplate->renderResponse('Admin/Index/Result');
    }

    public function generateAction(): ResponseInterface
    {
        $pages = [];
        foreach ($this->eventRepository->findPidsOfRecurringEvents() as $row) {
            foreach ($row as $pid) {
                $page = $this->pageRepository->getPage((int)$pid);
                if ($page !== []) {
                    $pages[] = $page;
                }
            }
        }

        $moduleTemplate = $this->createModuleTemplate();
        $moduleTemplate->assignMultiple([
            'folders' => $pages,
        ]);
        return $moduleTemplate->renderResponse('Admin/Index/Generate');
    }

    private function createModuleTemplate(): ModuleTemplate
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle($this->translate('backend_module_indexer_header'));
        return $moduleTemplate;
    }

    /**
     * Accepts YYYYMMDD or anything \DateTime understands (e.g. "-3 months") and returns YYYYMMDD.
     */
    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return (new \DateTime($value))->format('Ymd');
        } catch (\Exception) {
            return null;
        }
    }

    private function translate(string $key): string
    {
        return (string)(LocalizationUtility::translate(self::LLL . $key) ?? $key);
    }
}
