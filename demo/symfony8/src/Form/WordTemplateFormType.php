<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_combine;
use function array_fill;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function in_array;
use function str_replace;

/**
 * Dynamic FormType for the WordTemplateBundle demo.
 *
 * Symfony Form field names cannot contain dots, so each variable name read from the {@code .docx}
 * is sanitized via {@see sanitize()} (e.g. {@code chapter.number} → {@code chapter_DOT_number}).
 * The controller restores the dotted keys via {@see unsanitize()} when building the context array
 * passed to {@see WordTemplateProcessorInterface::process()}.
 *
 * Children:
 *  - {@code placeholders}: one text/textarea per simple variable (textarea when in {@code html_vars}).
 *  - {@code rows}: one {@see CollectionType} per row anchor, fixed at 5 entries (excess empties are dropped server-side).
 *  - {@code conditionals}: one checkbox per conditional block ({@see ConditionalBlock}).
 *  - {@code scalar_choices}: drives PHP-computed inline scalars (word A or B in the same paragraph).
 *  - {@code images}: filesystem paths for {@see ImageSource} placeholders.
 *  - {@code submit}: download as {@code .docx}.
 *  - {@code submit_pdf}: download as PDF (PhpWord + DomPDF; fidelity limited — see template note).
 */
final class WordTemplateFormType extends AbstractType
{
    /**
     * Replacement marker for dots in placeholder names — Symfony Form requires field names matching
     * {@code /^[a-zA-Z][a-zA-Z0-9_\-:]*$/}.
     */
    public const DOT_PLACEHOLDER = '_DOT_';

    /**
     * Number of editable row slots rendered per anchor (empty rows are dropped on submit).
     */
    private const ROW_SLOTS = 5;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $simpleVars */
        $simpleVars = $options['simple_vars'];
        /** @var list<string> $htmlVars */
        $htmlVars = $options['html_vars'];
        /** @var array<string, string> $defaults */
        $defaults = $options['defaults'];
        /** @var array<string, list<string>> $rowGroups */
        $rowGroups = $options['row_groups'];
        /** @var array<string, list<array<string, string>>> $defaultRows */
        $defaultRows = $options['default_rows'];
        /** @var array<string, string> $conditionalBlocks */
        $conditionalBlocks = $options['conditional_blocks'];
        /** @var array<string, bool> $defaultConditionals */
        $defaultConditionals = $options['default_conditionals'];
        /** @var list<string> $imagePlaceholders */
        $imagePlaceholders = $options['image_placeholders'];
        /** @var array<string, string> $defaultImages */
        $defaultImages = $options['default_images'];
        /** @var array<string, bool> $defaultScalarChoices */
        $defaultScalarChoices = $options['default_scalar_choices'];

        $placeholders = $builder->create('placeholders', FormType::class, [
            'compound'     => true,
            'inherit_data' => false,
            'label'        => false,
        ]);
        foreach ($simpleVars as $var) {
            $isHtml = in_array($var, $htmlVars, true);
            $placeholders->add(self::sanitize($var), $isHtml ? TextareaType::class : TextType::class, [
                'required'   => false,
                'data'       => $defaults[$var] ?? '',
                'empty_data' => '',
                'label'      => '${' . $var . '}',
                'label_html' => false,
                'attr'       => $isHtml
                    ? ['class' => 'form-control font-monospace small', 'rows' => 4, 'spellcheck' => 'false']
                    : ['class' => 'form-control'],
                'label_attr' => ['class' => 'form-label'],
            ]);
        }
        $builder->add($placeholders);

        $rows = $builder->create('rows', FormType::class, [
            'compound'     => true,
            'inherit_data' => false,
            'label'        => false,
        ]);
        foreach ($rowGroups as $anchor => $cells) {
            /** @var array<string, string> $cellMap sanitized field name → original cell name */
            $cellMap = [];
            foreach ($cells as $cell) {
                $cellMap[self::sanitize($cell)] = $cell;
            }

            /** @var list<array<string, string>> $sanitizedDefault */
            $sanitizedDefault = array_map(
                static fn (array $row): array => array_combine(
                    array_map(static fn (string $k): string => self::sanitize($k), array_keys($row)),
                    array_values($row),
                ),
                $defaultRows[$anchor] ?? [],
            );
            while (count($sanitizedDefault) < self::ROW_SLOTS) {
                $sanitizedDefault[] = array_combine(
                    array_keys($cellMap),
                    array_fill(0, count($cellMap), ''),
                );
            }

            $rows->add(self::sanitize($anchor), CollectionType::class, [
                'entry_type'    => RowEntryType::class,
                'entry_options' => [
                    'cell_map' => $cellMap,
                    'label'    => false,
                ],
                'data'         => $sanitizedDefault,
                'allow_add'    => false,
                'allow_delete' => false,
                'label'        => false,
            ]);
        }
        $builder->add($rows);

        $conditionals = $builder->create('conditionals', FormType::class, [
            'compound'     => true,
            'inherit_data' => false,
            'label'        => false,
        ]);
        foreach ($conditionalBlocks as $blockName => $label) {
            $conditionals->add(self::sanitize($blockName), CheckboxType::class, [
                'required'   => false,
                'data'       => $defaultConditionals[$blockName] ?? false,
                'label'      => $label,
                'label_attr' => ['class' => 'form-check-label'],
                'attr'       => ['class' => 'form-check-input'],
            ]);
        }
        $builder->add($conditionals);

        $scalarChoices = $builder->create('scalar_choices', FormType::class, [
            'compound'     => true,
            'inherit_data' => false,
            'label'        => false,
        ]);
        $scalarChoices->add('client_is_vip', CheckboxType::class, [
            'required'   => false,
            'data'       => $defaultScalarChoices['client_is_vip'] ?? false,
            'label'      => 'VIP client → sets ${client_tier_label} to Gold or Standard in PHP',
            'label_attr' => ['class' => 'form-check-label'],
            'attr'       => ['class' => 'form-check-input'],
        ]);
        $builder->add($scalarChoices);

        $images = $builder->create('images', FormType::class, [
            'compound'     => true,
            'inherit_data' => false,
            'label'        => false,
        ]);
        foreach ($imagePlaceholders as $imageVar) {
            $images->add(self::sanitize($imageVar), TextType::class, [
                'required'   => false,
                'data'       => $defaultImages[$imageVar] ?? '',
                'empty_data' => '',
                'label'      => '${' . $imageVar . '} (filesystem path)',
                'attr'       => ['class' => 'form-control font-monospace small'],
                'label_attr' => ['class' => 'form-label'],
            ]);
        }
        $builder->add($images);

        $builder->add('submit', SubmitType::class, [
            'label' => 'Download .docx',
            'attr'  => ['class' => 'btn btn-primary'],
        ]);

        $builder->add('submit_pdf', SubmitType::class, [
            'label' => 'Download PDF',
            'attr'  => ['class' => 'btn btn-outline-secondary'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'simple_vars'            => [],
            'html_vars'              => [],
            'row_groups'             => [],
            'conditional_blocks'     => [],
            'image_placeholders'     => [],
            'default_images'         => [],
            'default_scalar_choices' => [],
            'defaults'               => [],
            'default_rows'           => [],
            'default_conditionals'   => [],
            'data_class'             => null,
        ]);
        $resolver->setAllowedTypes('simple_vars', 'array');
        $resolver->setAllowedTypes('html_vars', 'array');
        $resolver->setAllowedTypes('row_groups', 'array');
        $resolver->setAllowedTypes('conditional_blocks', 'array');
        $resolver->setAllowedTypes('image_placeholders', 'array');
        $resolver->setAllowedTypes('default_images', 'array');
        $resolver->setAllowedTypes('default_scalar_choices', 'array');
        $resolver->setAllowedTypes('defaults', 'array');
        $resolver->setAllowedTypes('default_rows', 'array');
        $resolver->setAllowedTypes('default_conditionals', 'array');
    }

    public static function sanitize(string $name): string
    {
        return str_replace('.', self::DOT_PLACEHOLDER, $name);
    }

    public static function unsanitize(string $field): string
    {
        return str_replace(self::DOT_PLACEHOLDER, '.', $field);
    }
}
