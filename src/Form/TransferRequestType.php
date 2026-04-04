<?php

namespace App\Form;

use App\Model\TransferRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransferRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cin', TextType::class, [
                'required' => true,
                'attr' => [
                    'placeholder' => 'CIN du bénéficiaire',
                    'inputmode' => 'numeric',
                    'pattern' => '^\\d{8}$',
                    'maxlength' => 8,
                ],
            ])
            ->add('numeroCarte', TextType::class, [
                'mapped' => false,
                'required' => false,
                'disabled' => true,
                'attr' => [
                    'placeholder' => 'Numéro de carte (auto)',
                ],
            ])
            ->add('montant', NumberType::class, [
                'required' => true,
                'scale' => 2,
                'attr' => [
                    'placeholder' => 'Montant',
                    'step' => '0.01',
                    'min' => '0.01',
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => true,
                'attr' => [
                    'placeholder' => 'Description',
                    'rows' => 3,
                    'maxlength' => 100,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TransferRequest::class,
        ]);
    }
}
