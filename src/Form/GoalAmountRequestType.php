<?php

namespace App\Form;

use App\Model\GoalAmountRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GoalAmountRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('amount', NumberType::class, [
            'required' => true,
            'scale' => 2,
            'attr' => [
                'placeholder' => 'Montant à ajouter',
                'step' => '0.01',
                'min' => '0.01',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GoalAmountRequest::class,
        ]);
    }
}
