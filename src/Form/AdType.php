<?php

namespace App\Form;

use App\Entity\Ad;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de l\'annonce',
                'required' => true,
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Titre de l\'annonce',
                    'class' => 'form-control'
                ],
            ])
            ->add('imagePath', FileType::class, [
                'label' => 'Image de l\'annonce',
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/jpeg,image/png,image/webp,image/gif',
                ],
            ])
            ->add('duration', IntegerType::class, [
                'label' => 'Durée (en secondes)',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Durée en secondes',
                    'min' => '1',
                    'class' => 'form-control'
                ],
            ])
            ->add('rewardPoints', IntegerType::class, [
                'label' => 'Points de récompense',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Points offerts au clic',
                    'min' => '0',
                    'class' => 'form-control'
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ad::class,
        ]);
    }
}