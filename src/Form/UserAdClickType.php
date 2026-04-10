<?php

namespace App\Form;

use App\Entity\Ad;
use App\Entity\User;
use App\Entity\UserAdClick;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserAdClickType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'label' => 'Utilisateur',
                'class' => User::class,
                'choice_label' => fn(User $user) => $user->getFirstname() . ' ' . $user->getLastname(),
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ],
            ])
            ->add('ad', EntityType::class, [
                'label' => 'Annonce',
                'class' => Ad::class,
                'choice_label' => 'title',
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserAdClick::class,
        ]);
    }
}