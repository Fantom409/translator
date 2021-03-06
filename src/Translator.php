<?php

declare(strict_types=1);

namespace Yiisoft\Translator;

use Psr\EventDispatcher\EventDispatcherInterface;
use Yiisoft\Translator\Event\MissingTranslationCategoryEvent;
use Yiisoft\Translator\Event\MissingTranslationEvent;

class Translator implements TranslatorInterface
{
    private string $defaultCategory;
    private string $locale;
    private EventDispatcherInterface $eventDispatcher;
    private ?string $fallbackLocale;
    /**
     * @var Category[]
     */
    private array $categories = [];

    public function __construct(
        Category $defaultCategory,
        string $locale,
        EventDispatcherInterface $eventDispatcher,
        string $fallbackLocale = null
    ) {
        $this->defaultCategory = $defaultCategory->getName();
        $this->eventDispatcher = $eventDispatcher;
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale;

        $this->addCategorySource($defaultCategory);
    }

    public function addCategorySource(Category $category): void
    {
        $this->categories[$category->getName()] = $category;
    }

    /**
     * Sets the current application locale.
     *
     * @param string $locale
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function translate(
        string $id,
        array $parameters = [],
        string $category = null,
        string $locale = null
    ): string {
        $locale = $locale ?? $this->locale;

        $category = $category ?? $this->defaultCategory;

        if (empty($this->categories[$category])) {
            $this->eventDispatcher->dispatch(new MissingTranslationCategoryEvent($category));
            return $id;
        }

        $sourceCategory = $this->categories[$category];
        $message = $sourceCategory->getMessage($id, $locale, $parameters);

        if ($message === null) {
            $this->eventDispatcher->dispatch(new MissingTranslationEvent($sourceCategory->getName(), $locale, $id));

            $localeObject = new Locale($locale);
            $fallback = $localeObject->fallbackLocale();

            if ($fallback->asString() !== $localeObject->asString()) {
                return $this->translate($id, $parameters, $category, $fallback->asString());
            }

            if (!empty($this->fallbackLocale)) {
                $fallbackLocaleObject = (new Locale($this->fallbackLocale))->fallbackLocale();
                if ($fallbackLocaleObject->asString() !== $localeObject->asString()) {
                    return $this->translate($id, $parameters, $category, $fallbackLocaleObject->asString());
                }
            }

            $message = $id;
        }

        return $sourceCategory->format($message, $parameters, $locale);
    }
}
