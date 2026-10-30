<?php

declare(strict_types=1);

namespace App\Form;

use App\Conditionals\ConditionalsContextBuilder;
use App\Model\InvoiceStatus;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One field per PHP type covered by docs/USAGE.md "Conditionals by data type".
 *
 * The form data keeps the native types produced by each Symfony form type (bool, ?bool, ?int, ?float,
 * ?string, ?InvoiceStatus, ?DateTimeImmutable); {@see ConditionalsContextBuilder}
 * turns them into {@see ConditionalBlock} decisions.
 */
final class ConditionalsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $text  = ['attr' => ['class' => 'form-control'], 'label_attr' => ['class' => 'form-label'], 'required' => false];
        $check = ['attr' => ['class' => 'form-check-input'], 'label_attr' => ['class' => 'form-check-label'], 'required' => false];

        $builder
            ->add('customer_name', TextType::class, ['label' => 'Customer name (string, plain placeholder)'] + $text)
            ->add('is_vip', CheckboxType::class, ['label' => 'VIP customer (bool)'] + $check)
            ->add('terms_accepted', ChoiceType::class, [
                'label'       => 'Terms accepted (?bool)',
                'choices'     => ['Yes' => true, 'No' => false],
                'placeholder' => 'Not answered (null)',
                'attr'        => ['class' => 'form-select'],
            ] + $text)
            ->add('quantity', IntegerType::class, ['label' => 'Quantity (?int)'] + $text)
            ->add('discount', IntegerType::class, ['label' => 'Discount % (?int — 0 is shown, empty is hidden)'] + $text)
            ->add('total', NumberType::class, ['label' => 'Total EUR (?float)', 'scale' => 4] + $text)
            ->add('country', TextType::class, ['label' => 'Country code (string, case-insensitive)'] + $text)
            ->add('notes', TextareaType::class, ['label' => 'Notes (?string — blank is hidden)', 'attr' => ['class' => 'form-control', 'rows' => 2]] + $text)
            ->add('status', EnumType::class, [
                'label'       => 'Invoice status (backed enum — one block per case)',
                'class'       => InvoiceStatus::class,
                'placeholder' => 'No status (null)',
                'attr'        => ['class' => 'form-select'],
            ] + $text)
            ->add('due_date', DateType::class, [
                'label'  => 'Due date (?DateTimeImmutable — overdue when before today and not paid)',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
            ] + $text)
            ->add('lines', TextareaType::class, [
                'label' => 'Order lines (array — one per line, "CODE: description"; empty shows "No items")',
                'attr'  => ['class' => 'form-control font-monospace', 'rows' => 3],
            ] + $text)
            ->add('plan', TextType::class, ['label' => 'Plan (free string — basic / pro / enterprise, anything else = other)'] + $text)
            ->add('preview', SubmitType::class, ['label' => 'Preview decisions', 'attr' => ['class' => 'btn btn-outline-primary']])
            ->add('download', SubmitType::class, ['label' => 'Download .docx', 'attr' => ['class' => 'btn btn-primary']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
