<?php

declare(strict_types=1);

namespace K3ssen\VueBundle\Form\Extension;

use Doctrine\Common\Collections\Collection;
use K3ssen\VueBundle\Storage\VueDataStorage;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony Form Type Extension that adds Vue capabilities to all form types.
 * Setting the `v_model` option (with the name of the desired var) makes that fields data accessible from Vue.
 */
class VueFormTypeExtension extends AbstractTypeExtension
{
    private VueDataStorage $vueDataStorage;

    public function __construct(VueDataStorage $vueDataStorage)
    {
        $this->vueDataStorage = $vueDataStorage;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('v_model', null);
        $resolver->setAllowedTypes('v_model', ['null', 'string']);
    }

    public static function getExtendedTypes(): array
    {
        return [FormType::class];
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        if ($vModelName = $options['v_model'] ?? null) {
            $expanded = $view->vars['expanded'] ?? false;
            $splitDateTime = in_array('datetime', $view->vars['block_prefixes'], true) && ($options['widget'] ?? null) !== 'single_text';
            if (!$expanded && !$splitDateTime) {
                $view->vars['attr']['v-model'] = $vModelName;
            }
            $data = $form->getData();
            $vueModelData = $data;
            $vueModelArrayData = [];
            // In case of choice, the vue-model should contain the value of the selected choice.
            /** @var ChoiceView $choice */
            foreach ($view->vars['choices'] ?? [] as $choice) {
                if ($choice->data === $data) {
                    $vueModelData = $choice->value;
                } elseif (
                    ($data instanceof Collection && $data->contains($choice->data))
                    || (is_array($data) && in_array($choice->data, $data, true))
                ) {
                    $vueModelArrayData[] = $choice->value;
                }
            }
            if ($expanded) {
                foreach ($view as $childView) {
                    $childView->vars['attr']['v-model'] = $vModelName;
                }
            }
            if ($splitDateTime) {
                $view->children['date']->vars['attr']['v-model'] = $vModelName . '.date';
                if ($view->children['time']->children) {
                    $view->children['time']->children['hour']->vars['attr']['v-model'] = $vModelName . '.time.hour';
                    $view->children['time']->children['minute']->vars['attr']['v-model'] = $vModelName . '.time.minute';
                } else {
                    $view->children['time']->vars['attr']['v-model'] = $vModelName . '.time';
                }
            }
            if ($vueModelData instanceof \DateTimeInterface && $form->getViewData()) {
                $vueModelData = $form->getViewData();
                // In case of DateTime the date field doesn't get converted properly from the parent level
                if (is_array($vueModelData) && array_key_exists('date', $vueModelData)) {
                    $vueModelData['date'] = $form->get('date')->getViewData();
                }
            }
            $multiple = $view->vars['multiple'] ?? false;
            $this->vueDataStorage->addData($vModelName, $multiple ? $vueModelArrayData : $vueModelData);
        }
    }
}
