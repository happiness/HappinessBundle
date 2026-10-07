<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\EventSubscriber;

use App\Entity\ProjectMeta;
use App\Event\ProjectMetaDefinitionEvent;
use App\Event\ThemeEvent;
use KimaiPlugin\HappinessBundle\Configuration\RetainerBalanceFields;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Project fields to mark a project as retainer with a number of hours per month.
 * The hours and start month inputs are only shown while the checkbox is checked.
 */
final class RetainerBalanceMetaSubscriber implements EventSubscriberInterface
{
    public const ATTRIBUTE = 'data-happiness-retainer';

    public static function getSubscribedEvents(): array
    {
        return [
            ProjectMetaDefinitionEvent::class => ['loadMeta', 190],
            ThemeEvent::JAVASCRIPT => ['onJavascript', 100],
        ];
    }

    public function loadMeta(ProjectMetaDefinitionEvent $event): void
    {
        $project = $event->getEntity();

        $project->setMetaField((new ProjectMeta())
            ->setName(RetainerBalanceFields::ENABLED)
            ->setLabel('happiness.retainer_balance.enabled')
            ->setType(CheckboxType::class)
            ->setOptions($this->options(['attr' => [self::ATTRIBUTE => 'enabled']]))
            ->setIsVisible(true));

        $project->setMetaField((new ProjectMeta())
            ->setName(RetainerBalanceFields::HOURS)
            ->setLabel('happiness.retainer_balance.hours')
            ->setType(NumberType::class)
            ->setOptions($this->options(['scale' => 2, 'html5' => true, 'attr' => [self::ATTRIBUTE => 'hours', 'min' => 0, 'step' => 0.25]]))
            ->addConstraint(new PositiveOrZero())
            ->setIsVisible(true));

        $project->setMetaField((new ProjectMeta())
            ->setName(RetainerBalanceFields::START)
            ->setLabel('happiness.retainer_balance.start')
            ->setType(TextType::class)
            ->setOptions($this->options(['help' => 'happiness.retainer_balance.start_help', 'attr' => [self::ATTRIBUTE => 'start', 'placeholder' => 'YYYY-MM', 'maxlength' => 7]]))
            ->addConstraint(new Regex(pattern: RetainerBalanceFields::START_PATTERN, message: 'Use the format YYYY-MM, for example 2026-10.'))
            ->setIsVisible(true));

        $project->setMetaField((new ProjectMeta())
            ->setName(RetainerBalanceFields::TEXT)
            ->setLabel('happiness.retainer_balance.text')
            ->setType(TextareaType::class)
            ->setOptions($this->options(['help' => 'happiness.retainer_balance.text_help', 'attr' => [self::ATTRIBUTE => 'text', 'rows' => 4]]))
            ->setIsVisible(true));
    }

    public function onJavascript(ThemeEvent $event): void
    {
        $attribute = self::ATTRIBUTE;
        // The project form is loaded via AJAX into a modal, so this cannot wait for DOMContentLoaded:
        // it has to react whenever the form appears in the page.
        $event->addContent(<<<HTML
            <script>
            (function () {
                var selector = function (name) { return '[{$attribute}="' + name + '"]'; };
                var apply = function () {
                    var toggle = document.querySelector(selector('enabled'));
                    if (!toggle) {
                        return;
                    }
                    var wrapper = document.getElementById('happiness-retainer-fields');
                    var rows = [];
                    if (!wrapper) {
                        rows = ['hours', 'start'].map(function (name) {
                            var input = document.querySelector(selector(name));
                            return input ? input.closest('.mb-3') : null;
                        }).filter(Boolean);
                        if (rows.length === 2) {
                            wrapper = document.createElement('div');
                            wrapper.id = 'happiness-retainer-fields';
                            wrapper.className = 'row';
                            rows[0].parentNode.insertBefore(wrapper, rows[0]);
                            rows.forEach(function (row) {
                                row.classList.add('col-md-6');
                                wrapper.appendChild(row);
                            });
                        }
                    }
                    var text = document.querySelector(selector('text'));
                    var textRow = text ? text.closest('.mb-3') : null;
                    (wrapper ? [wrapper] : rows).concat(textRow ? [textRow] : []).forEach(function (el) {
                        el.classList.toggle('d-none', !toggle.checked);
                    });
                };
                document.addEventListener('change', function (event) {
                    if (event.target && event.target.matches && event.target.matches(selector('enabled'))) {
                        apply();
                    }
                });
                new MutationObserver(apply).observe(document.documentElement, {childList: true, subtree: true});
                apply();
            })();
            </script>
            HTML);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function options(array $options): array
    {
        return array_merge(['required' => false, 'translation_domain' => 'happiness'], $options);
    }
}
