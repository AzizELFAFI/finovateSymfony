<?php

namespace App\Form;

use App\Model\CreateProjectRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreateProjectRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'required' => true,
                'label' => 'Titre',
                'attr' => [
                    'maxlength' => 150,
                    'placeholder' => 'Nom du projet',
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => true,
                'label' => 'Description',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Décrivez le projet et son objectif',
                ],
            ])
            ->add('goalAmount', NumberType::class, [
                'required' => true,
                'label' => 'Objectif de financement',
                'scale' => 2,
                'attr' => [
                    'placeholder' => 'Montant cible',
                    'step' => '0.01',
                    'min' => '0.01',
                ],
            ])
            ->add('deadline', DateType::class, [
                'required' => false,
                'label' => 'Date limite (optionnel)',
                'widget' => 'single_text',
                'attr' => [
                    'min' => (new \DateTime('tomorrow'))->format('Y-m-d'),
                ],
            ])
            ->add('category', TextType::class, [
                'required' => false,
                'label' => 'Catégorie (optionnel)',
                'attr' => [
                    'maxlength' => 100,
                    'placeholder' => 'Ex. Énergie, Immobilier…',
                ],
            ])
            ->add('image', FileType::class, [
                'required' => false,
                'label' => 'Image du projet (optionnel)',
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateProjectRequest::class,
        ]);
    }
}
