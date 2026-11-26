<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\ViewHelpers;

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
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders a view helper and records the template variables each time the children are rendered.
 */
trait ViewHelperTestTrait
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $renderedVariables = [];

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $variables
     */
    private function renderViewHelper(AbstractViewHelper $viewHelper, array $arguments, array $variables = []): mixed
    {
        $this->renderedVariables = [];
        $context = new RenderingContext();
        foreach ($variables as $name => $value) {
            $context->getVariableProvider()->add($name, $value);
        }
        $viewHelper->setRenderingContext($context);
        $viewHelper->initializeArguments();
        $viewHelper->setArguments($arguments);
        $viewHelper->setRenderChildrenClosure(function () use ($context): string {
            $this->renderedVariables[] = $context->getVariableProvider()->getAll();
            return '|';
        });
        return $viewHelper->render();
    }
}
