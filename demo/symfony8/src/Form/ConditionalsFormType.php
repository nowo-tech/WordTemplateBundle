<?php

declare(strict_types=1);

namespace App\Form;

use App\Conditionals\ConditionalsContextBuilder;
use App\Model\InvoiceStatus;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * REQ-DEMO-015: a FormKit form (profile `demo`) with CSRF. One field per PHP type covered by docs/USAGE.md "Conditionals by data type".
 *
 * The form data keeps the native types produced by each Symfony form type (bool, ?bool, ?int, ?float,
 * ?string, ?InvoiceStatus, ?DateTimeImmutable); {@see ConditionalsContextBuilder}
 * turns them into {@see ConditionalBlock} decisions.
 */
#[FormKitConfig('demo')]
final class ConditionalsFormType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $base = ['required' => false, 'translation_domain' => false, 'placeholder' => false, 'help' => false];

        $this->addText($builder, 'customer_name', ['label' => 'Customer name (string, plain placeholder)'] + $base);
        $this->addCheckbox($builder, 'is_vip', ['label' => 'VIP customer (bool)'] + $base);
        $this->addChoice($builder, 'terms_accepted', [
            'label'       => 'Terms accepted (?bool)',
            'choices'     => ['Yes' => true, 'No' => false],
            'placeholder' => 'Not answered (null)',
        ] + $base);
        $this->addInteger($builder, 'quantity', ['label' => 'Quantity (?int)'] + $base);
        $this->addInteger($builder, 'discount', ['label' => 'Discount % (?int — 0 is shown, empty is hidden)'] + $base);
        $this->addNumber($builder, 'total', ['label' => 'Total EUR (?float)', 'scale' => 4] + $base);
        $this->addText($builder, 'country', ['label' => 'Country code (string, case-insensitive)'] + $base);
        $this->addTextarea($builder, 'notes', ['label' => 'Notes (?string — blank is hidden)', 'attr' => ['rows' => 2]] + $base);
        $this->addWithDefaults($builder, 'status', EnumType::class, [
            'label'       => 'Invoice status (backed enum — one block per case)',
            'class'       => InvoiceStatus::class,
            'placeholder' => 'No status (null)',
        ] + $base);
        $this->addWithDefaults($builder, 'due_date', DateType::class, [
            'label'  => 'Due date (?DateTimeImmutable — overdue when before today and not paid)',
            'widget' => 'single_text',
            'input'  => 'datetime_immutable',
        ] + $base);
        $this->addTextarea($builder, 'lines', [
            'label' => 'Order lines (array — one per line, "CODE: description"; empty shows "No items")',
            'attr'  => ['rows' => 3],
        ] + $base);
        $this->addText($builder, 'plan', ['label' => 'Plan (free string — basic / pro / enterprise, anything else = other)'] + $base);
        $this->addWithDefaults($builder, 'preview', SubmitType::class, [
            'label'              => 'Preview decisions',
            'translation_domain' => false,
            'attr'               => ['class' => 'nowo-ui-btn nowo-ui-btn--outline'],
        ]);
        $this->addWithDefaults($builder, 'download', SubmitType::class, [
            'label'              => 'Download .docx',
            'translation_domain' => false,
            'attr'               => ['class' => 'nowo-ui-btn nowo-ui-btn--primary'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
