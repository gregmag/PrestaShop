<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShopBundle\Twig;

use PrestaShop\PrestaShop\Core\Util\Inflector;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TranslationsExtension extends AbstractExtension
{
    /**
     * @var TranslatorInterface
     */
    public $translator;

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var string
     */
    private $theme;

    private Environment $twig;

    public function __construct(RouterInterface $router, Environment $twig)
    {
        $this->router = $router;
        $this->twig = $twig;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('getTranslationsTree', [$this, 'getTranslationsTree']),
            new TwigFunction('getTranslationsForms', [$this, 'getTranslationsForms']),
        ];
    }

    /**
     * Returns concatenated edit translation forms.
     *
     * @param array $translationsTree
     * @param string|null $themeName
     *
     * @return string
     */
    public function getTranslationsForms(array $translationsTree, $themeName = null)
    {
        $output = '';
        $viewProperties = $this->getSharedEditFormViewProperties();
        $viewProperties['is_search_results'] = true;
        $this->theme = $themeName;

        foreach ($translationsTree as $tree) {
            $output .= $this->concatenateEditTranslationForm($tree, $viewProperties);
        }

        return $output;
    }

    public function concatenateEditTranslationForm($subtree, $viewProperties)
    {
        $output = '';
        $hasMessages = $this->hasMessages($subtree);

        if ($hasMessages) {
            $keysSubTreeMessages = array_keys($subtree['__messages']);
            $camelizedDomain = reset($keysSubTreeMessages);
            $messages = $subtree['__messages'][$camelizedDomain];

            foreach ($messages as $translationKey => $translation) {
                $viewProperties['camelized_domain'] = $camelizedDomain;
                $viewProperties['translation_key'] = $translationKey;
                $viewProperties['translation'] = $translation;

                $output .= $this->renderEditTranslationForm($viewProperties);
            }
        } else {
            foreach ($subtree as $tree) {
                $output .= $this->concatenateEditTranslationForm($tree, $viewProperties);
            }
        }

        if ($hasMessages && count($subtree) > 1) {
            unset($subtree['__messages']);
            $output .= $this->concatenateEditTranslationForm($subtree, $viewProperties);
        }

        return $output;
    }

    /**
     * Returns a tree of translations key values.
     *
     * @param array $translationsTree
     * @param string|null $themeName
     *
     * @return string
     */
    public function getTranslationsTree(array $translationsTree, $themeName = null)
    {
        $this->theme = $themeName;

        $output = '';

        foreach ($translationsTree as $topLevelDomain => $tree) {
            $output .= $this->concatenateSubtreeHeader($topLevelDomain, $tree);
        }

        return $output;
    }

    /**
     * @param array $tree
     * @param int $level
     *
     * @return string
     */
    public function makeSubtree($tree, $level = 3)
    {
        $output = '';
        $messagesSubtree = $this->hasMessages($tree);

        if ($messagesSubtree) {
            $keysSubTreeMessages = array_keys($tree['__messages']);
            $camelizedDomain = reset($keysSubTreeMessages);
            $messagesTree = $tree['__messages'][$camelizedDomain];

            $formIndex = 0;
            $pageIndex = 1;
            $itemsPerPage = 25;
            $output .= '<div class="page" data-status="active" data-page-index="1">';

            $viewProperties = $this->getSharedEditFormViewProperties();

            foreach ($messagesTree as $translationKey => $translation) {
                $viewProperties['camelized_domain'] = $camelizedDomain;
                $viewProperties['translation_key'] = $translationKey;
                $viewProperties['translation'] = $translation;

                $output .= $this->renderEditTranslationForm($viewProperties);

                $isLastPage = $formIndex + 1 === count($messagesTree);

                if ($isLastPage) {
                    $output .= '</div>';
                } elseif ((0 === $formIndex % $itemsPerPage) && ($formIndex > 0)) {
                    ++$pageIndex;

                    // Close div with page class
                    $output .= '</div>';
                    $output .= '<div class="page hide" data-status="inactive" data-page-index="' . $pageIndex . '">';
                }

                ++$formIndex;
            }

            // Close div with page class when no message is available
            if (count($messagesTree) === 0) {
                $output .= '</div>';
            }
        } else {
            foreach ($tree as $subdomain => $subtree) {
                $output .= $this->concatenateSubtreeHeader($subdomain, $subtree, $level);
            }
        }

        return $output;
    }

    /**
     * @return array
     */
    protected function getSharedEditFormViewProperties()
    {
        return [
            'action' => $this->router->generate('admin_international_translations_edit'),
            'label_edit' => $this->translator->trans('Save', [], 'Admin.Actions'),
            'label_reset' => $this->translator->trans('Reset', [], 'Admin.Actions'),
            'notification_success' => $this->translator->trans(
                'Translation successfully updated',
                [],
                'Admin.International.Notification'
            ),
            'notification_error' => $this->translator->trans(
                'Failed to update translation',
                [],
                'Admin.International.Notification'
            ),
        ];
    }

    /**
     * @param array $properties
     *
     * @return mixed|string
     */
    protected function renderEditTranslationForm($properties)
    {
        [$domain, $locale] = explode('.', $properties['camelized_domain']);
        $translationValue = $this->getTranslationValue($properties['translation']);
        $defaultTranslationValue = $this->getDefaultTranslationValue(
            $properties['translation_key'],
            $domain,
            $locale,
            $properties['translation']
        );

        $isSearchResults = false;

        if (array_key_exists('is_search_results', $properties)) {
            $isSearchResults = $properties['is_search_results'];
        }

        $breadcrumbParts = explode('_', Inflector::getInflector()->tableize($domain));

        return $this->twig->render(
            '@PrestaShop/Admin/Translations/include/form-edit-message.html.twig',
            [
                'default_translation_value' => $defaultTranslationValue,
                'domain' => $domain,
                'edited_translation_value' => $translationValue,
                'is_translated' => '' !== $translationValue,
                'action' => $properties['action'],
                'label_edit' => $properties['label_edit'],
                'label_reset' => $properties['label_reset'],
                'locale' => $locale,
                'notification_error' => $properties['notification_error'],
                'notification_success' => $properties['notification_success'],
                'translation_key' => $properties['translation_key'],
                'hash' => $this->getTranslationHash($domain, $properties['translation_key']),
                'theme' => $this->theme,
                'breadcrumb_parts' => $breadcrumbParts,
                'is_search_results' => $isSearchResults,
            ]
        );
    }

    protected function getTranslationHash($domain, $translationKey)
    {
        return md5($domain . $translationKey);
    }

    /**
     * @param string $translationKey
     * @param string $domain
     * @param string $locale
     * @param array $translationValue
     *
     * @return array
     */
    protected function getDefaultTranslationValue($translationKey, $domain, $locale, $translationValue)
    {
        $defaultTranslationValue = $this->translator->trans($translationKey, [], $domain, $locale);

        // Extract default translation value from xliff files for reset
        if (is_array($translationValue)) {
            $defaultTranslationValue = $translationValue['xlf'];
        }

        return $defaultTranslationValue;
    }

    /**
     * @param array $translation
     *
     * @return mixed
     */
    protected function getTranslationValue($translation)
    {
        return !empty($translation['db']) ? $translation['db'] : $translation['xlf'];
    }

    /**
     * @param array $tree
     *
     * @return bool
     */
    protected function hasMessages($tree)
    {
        return array_key_exists('__messages', $tree);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'twig_translations_extension';
    }

    /**
     * @param string $subdomain
     * @param array $subtree
     * @param int $level
     *
     * @return string
     */
    protected function concatenateSubtreeHeader($subdomain, $subtree, $level = 2)
    {
        $hasMessagesSubtree = $this->hasMessages($subtree);
        $subject = $subdomain;

        $id = null;
        if ($hasMessagesSubtree) {
            $id = $this->parseDomain($subtree);
        }

        $output = $this->tagSubject($subject, $hasMessagesSubtree, $id);

        if (!$hasMessagesSubtree) {
            $output = str_replace(
                '{{ missing translations warning }}',
                $this->translator->trans('%d missing', [], 'Admin.International.Feature'),
                $output
            );
        } else {
            $output = $this->replaceWarningPlaceholder($output, $subtree);
        }

        if ($hasMessagesSubtree) {
            $output .= $this->twig->render(
                '@PrestaShop/Admin/Translations/include/button-toggle-messages-visibility.html.twig',
                [
                    'label_show_messages' => $this->translator->trans('Show messages', [], 'Admin.International.Feature'),
                    'label_hide_messages' => $this->translator->trans('Hide messages', [], 'Admin.International.Feature'),
                ]
            );

            $output .= $this->getNavigation($this->parseDomain($subtree));
        }

        $formStart = $this->getTranslationsFormStart($subtree, $output);
        $output = $this->twig->render(
            '@PrestaShop/Admin/Translations/include/translations-form-end.html.twig',
            [
                'form_start' => $formStart,
                'subtree' => $this->makeSubtree($subtree, $level + 1),
            ]
        );

        if ($hasMessagesSubtree) {
            // Close div with translation-domain class
            $output .= '</div>';

            // A subtree with messages contains at least a subdomain
            if (count($subtree) > 1) {
                unset($subtree['__messages']);
                $output .= $this->concatenateSubtreeHeader($subdomain, $subtree, $level);
            }
        }

        return $output;
    }

    /**
     * @param array $subtree
     * @param string $output
     *
     * @return string
     */
    protected function getTranslationsFormStart(&$subtree, $output)
    {
        $id = '';
        $parentAttribute = ' class="subdomains hide"';
        if (array_key_exists('__fixed_length_id', $subtree)) {
            $fixedLengthId = $subtree['__fixed_length_id'];
            unset($subtree['__fixed_length_id']);
            $id = ' id="' . $fixedLengthId . '" ';
            $parentAttribute = ' data-parent-of="' . $fixedLengthId . '"';
        }

        $domainAttribute = '';
        if (array_key_exists('__domain', $subtree)) {
            $domainAttribute = ' data-domain="' . $subtree['__domain'] . '" ';
            unset($subtree['__domain']);
        }

        $totalTranslationsAttribute = '';
        if (array_key_exists('__messages', $subtree)) {
            $totalTranslations = count(array_values($subtree['__messages'])[0]);
            $totalTranslationsAttribute = ' data-total-translations="' . $this->translator->trans(
                '%nb_translations% expressions',
                ['%nb_translations%' => $totalTranslations],
                'Admin.International.Feature'
            ) . '"';
        }

        $missingTranslationsAttribute = '';
        if (array_key_exists('__metadata', $subtree)) {
            $missingTranslations = $subtree['__metadata']['missing_translations'];
            $missingTranslationsAttribute = ' data-missing-translations="' . $missingTranslations . '"';
            unset($subtree['__metadata']);
        }

        return $this->twig->render(
            '@PrestaShop/Admin/Translations/include/translations-form-start.html.twig',
            [
                'id' => $id,
                'domain' => $domainAttribute,
                'parent' => $parentAttribute,
                'total_translations' => $totalTranslationsAttribute,
                'missing_translations' => $missingTranslationsAttribute,
                'title' => $output,
            ]
        );
    }

    /**
     * @param string $output
     * @param array $subtree
     *
     * @return string
     */
    protected function replaceWarningPlaceholder($output, $subtree)
    {
        $missingTranslationsMessage = '';
        $missingTranslationsLongMessage = '';
        $missingTranslationsClass = '';
        if (array_key_exists('__metadata', $subtree) && $subtree['__metadata']['missing_translations'] > 0) {
            $missingTranslationsCount = $subtree['__metadata']['missing_translations'];
            $domain = $subtree['__metadata']['domain'];

            $missingTranslationsMessage =
                '<div class="missing-translations-short-message pull-right hide">' .
                $this->translator->trans(
                    '%nb_translations% missing',
                    ['%nb_translations%' => $missingTranslationsCount],
                    'Admin.International.Feature'
                ) .
                '</div>';
            $missingTranslationsLongMessage =
                '<div class="missing-translations-long-message hide">' .
                $this->translator->trans(
                    '%nb_translations% translations are missing in %domain%',
                    [
                        '%nb_translations%' => $missingTranslationsCount,
                        '%domain%' => $domain,
                    ],
                    'Admin.International.Feature'
                ) .
                '</div>';
            $missingTranslationsClass = ' missing-translations';
        }

        $warning = str_replace(
            [
                '{{ missing translations message }}',
                '{{ missing translations long message }}',
            ],
            [
                $missingTranslationsMessage,
                $missingTranslationsLongMessage,
            ],
            '{{ missing translations message }}{{ missing translations long message }}'
        );

        return str_replace(
            [
                '{{ missing translations warning }}',
                '{{ missing translations class }}',
            ],
            [
                $warning,
                $missingTranslationsClass,
            ],
            $output
        );
    }

    /**
     * @param array $subtree
     *
     * @return string
     */
    protected function parseDomain($subtree)
    {
        [$camelizedDomain] = $subtree['__messages'];
        [$domain] = explode('.', $camelizedDomain);

        return $domain;
    }

    /**
     * @param mixed $id
     *
     * @return string
     */
    protected function getNavigation($id)
    {
        return $this->twig->render(
            '@PrestaShop/Admin/Translations/include/pagination-bar.html.twig',
            ['page_id' => $id]
        );
    }

    /**
     * @param string $subject
     * @param bool $isLastChild
     * @param string|null $id
     *
     * @return string
     */
    protected function tagSubject($subject, $isLastChild, $id = null)
    {
        if ($isLastChild) {
            $openingTag = '<h2 class="domain-part">' .
                '<span class="delegate-toggle-messages{{ missing translations class }}">';
            $closingTag = '</span>{{ missing translations warning }}</h2>';
        } else {
            $openingTag = '<h2 class="domain-first-part"><i class="material-icons">&#xE315;</i><span>';
            $closingTag = '</span>' .
                '<div class="domain-actions">' .
                '<span class="missing-translations pull-right hide">' .
                '{{ missing translations warning }}' .
                '</span>' .
                '</div>' .
                '</h2>';
        }

        if ($id) {
            $openingTag = '<span id="_' . $id . '">';
            $closingTag = '</span>';

            if (!$isLastChild) {
                $openingTag = '<h2>' . $openingTag;
                $closingTag = $closingTag . '</h2>';
            }
        }

        return $openingTag . $subject . $closingTag;
    }
}
