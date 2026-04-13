<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResetPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('password', PasswordType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le mot de passe est obligatoire.']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    ]),
                ],
                'attr' => [
                    'placeholder' => 'Nouveau mot de passe',
                ],
            ])
            ->add('password_confirm', PasswordType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'La confirmation du mot de passe est obligatoire.']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'La confirmation du mot de passe doit contenir au moins {{ limit }} caractères.',
                    ]),
                ],
                'attr' => [
                    'placeholder' => 'Confirmer le mot de passe',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'reset_password',
        ]);
    }
}
