<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * REQ-DEMO-015: FormKit entry (profile `demo`) inside a {@see CollectionType}.
 *
 * Renders one input per cell from the {@code cell_map} option (sanitized field name → original placeholder name).
 */
#[FormKitConfig('demo')]
final class RowEntryType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $cellMap */
        $cellMap = $options['cell_map'];

        foreach ($cellMap as $sanitized => $original) {
            $this->addText($builder, $sanitized, [
                'required'           => false,
                'label'              => $original,
                'empty_data'         => '',
                'translation_domain' => false,
                'placeholder'        => false,
                'help'               => false,
                'attr'               => ['placeholder' => $original],
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
