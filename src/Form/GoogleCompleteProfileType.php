<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

final class GoogleCompleteProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est obligatoire.']),
                    new Length(['min' => 3, 'minMessage' => 'Le prénom doit contenir au moins {{ limit }} caractères.']),
                    new Regex(['pattern' => "/^[\\p{L} .'-]+$/u", 'message' => 'Le prénom doit être une chaîne de caractères valide.']),
                ],
                'attr' => ['placeholder' => 'Prénom'],
            ])
            ->add('lastname', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire.']),
                    new Length(['min' => 3, 'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.']),
                    new Regex(['pattern' => "/^[\\p{L} .'-]+$/u", 'message' => 'Le nom doit être une chaîne de caractères valide.']),
                ],
                'attr' => ['placeholder' => 'Nom'],
            ])
            ->add('birthdate', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'La date de naissance est obligatoire.']),
                    new LessThanOrEqual(['value' => '-18 years', 'message' => 'Vous devez avoir au moins 18 ans.']),
                ],
            ])
            ->add('cin', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le CIN est obligatoire.']),
                    new Regex(['pattern' => '/^\\d{8}$/', 'message' => 'Le CIN doit contenir exactement 8 chiffres.']),
                ],
                'attr' => ['placeholder' => 'CIN (8 chiffres)'],
            ])
            ->add('phone_number', IntegerType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le numéro de téléphone est obligatoire.']),
                    new Range(['min' => 10000000, 'max' => 99999999, 'notInRangeMessage' => 'Le numéro de téléphone doit contenir exactement 8 chiffres.']),
                ],
                'attr' => ['placeholder' => 'Téléphone (8 chiffres)'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'google_complete_profile',
        ]);
    }
}
