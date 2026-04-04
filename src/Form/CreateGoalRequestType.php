<?php

namespace App\Form;

use App\Model\CreateGoalRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreateGoalRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'required' => true,
                'attr' => [
                    'maxlength' => 40,
                    'minlength' => 3,
                    'placeholder' => 'Titre du goal',
                ],
            ])
            ->add('deadline', DateType::class, [
                'required' => true,
                'widget' => 'single_text',
            ])
            ->add('targetAmount', NumberType::class, [
                'required' => true,
                'scale' => 2,
                'attr' => [
                    'placeholder' => 'Montant objectif',
                    'step' => '0.01',
                    'min' => '0.01',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateGoalRequest::class,
        ]);
    }
}
