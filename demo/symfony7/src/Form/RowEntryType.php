<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Single entry inside a {@see \Symfony\Component\Form\Extension\Core\Type\CollectionType}.
 *
 * Renders one input per cell from the {@code cell_map} option (sanitized field name → original placeholder name).
 */
final class RowEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $cellMap */
        $cellMap = $options['cell_map'];

        foreach ($cellMap as $sanitized => $original) {
            $builder->add($sanitized, TextType::class, [
                'required'   => false,
                'label'      => $original,
                'empty_data' => '',
                'attr'       => ['class' => 'form-control form-control-sm', 'placeholder' => $original],
                'label_attr' => ['class' => 'form-label small'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'cell_map'   => [],
        ]);
        $resolver->setAllowedTypes('cell_map', 'array');
    }
}
