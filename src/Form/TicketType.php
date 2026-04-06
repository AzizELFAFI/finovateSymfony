<?php

namespace App\Form;

use App\Entity\Ticket;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class TicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', TextType::class , [
            'constraints' => [
                new NotBlank([
                    'message' => 'Le type de ticket (projet) est obligatoire.',
                ]),
                new Length([
                    'min' => 3,
                    'minMessage' => 'Le type doit faire au moins {{ limit }} caractères.',
                    'max' => 100,
                    'maxMessage' => 'Le type ne peut pas dépasser {{ limit }} caractères.',
                ]),
            ],
            'attr' => ['placeholder' => 'Ex: Frontend, Base de données...']
        ])
            ->add('description', TextareaType::class , [
            'constraints' => [
                new NotBlank([
                    'message' => 'Veuillez décrire le problème ou projet.',
                ]),
                new Length([
                    'min' => 10,
                    'minMessage' => 'La description est trop courte. Il faut au moins {{ limit }} caractères.',
                ]),
            ],
            'attr' => ['rows' => 5, 'placeholder' => 'Détaillez votre ticket ici...']
        ])
            ->add('priorite', ChoiceType::class , [
            'choices' => [
                'Basse (Low)' => 'LOW',
                'Moyenne (Medium)' => 'MEDIUM',
                'Haute (High)' => 'HIGH',
            ],
            'constraints' => [
                new NotBlank([
                    'message' => 'La priorité est recquise.',
                ]),
            ],
        ])
            ->add('statut', ChoiceType::class , [
            'choices' => [
                'NOUVEAU' => 'NOUVEAU',
                'EN COURS' => 'EN_COURS',
                'RESOLU' => 'RESOLU',
                'CLOSED' => 'CLOSED',
            ],
            'constraints' => [
                new NotBlank([
                    'message' => 'Le statut du ticket est obligatoire.',
                ]),
            ],
        ])
            ->add('userId', IntegerType::class , [
            'constraints' => [
                new NotBlank([
                    'message' => 'L\'ID utilisateur est obligatoire.',
                ]),
                new Positive([
                    'message' => 'L\'ID utilisateur doit être un nombre positif.',
                ]),
            ],
        ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ticket::class ,
        ]);
    }
}
