<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TypeDefinitionException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormFactory implements FormFactoryInterface
{
    /**
     * @var FormRegistryInterface
     */
    private $registry;

    public function __construct(FormRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function create($type, $data = null, array $options = array(), FormBuilderInterface $parent = null)
    {
        return $this->createBuilder($type, $data, $options, $parent)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createNamed($name, $type, $data = null, array $options = array(), FormBuilderInterface $parent = null)
    {
        return $this->createNamedBuilder($name, $type, $data, $options, $parent)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createForProperty($class, $property, $data = null, array $options = array(), FormBuilderInterface $parent = null)
    {
        return $this->createBuilderForProperty($class, $property, $data, $options, $parent)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilder($type, $data = null, array $options = array(), FormBuilderInterface $parent = null)
    {
        $name = $type instanceof FormTypeInterface || $type instanceof ResolvedFormTypeInterface
            ? $type->getName()
            : $type;

        return $this->createNamedBuilder($name, $type, $data, $options, $parent);
    }

    /**
     * {@inheritdoc}
     */
    public function createNamedBuilder($name, $type, $data = null, array $options = array(), FormBuilderInterface $parent = null)
    {
        if (null !== $data && !array_key_exists('data', $options)) {
            $options['data'] = $data;
        }

        if ($type instanceof ResolvedFormTypeInterface) {
            $this->registry->addType($type);
        } elseif ($type instanceof FormTypeInterface) {
            $type = $this->registry->resolveType($type);
            $this->registry->addType($type);
        } elseif (is_string($type)) {
            $type = $this->registry->getType($type);
        } else {
            throw new UnexpectedTypeException($type, 'string, Symfony\Component\Form\ResolvedFormTypeInterface or Symfony\Component\Form\FormTypeInterface');
        }

        return $type->createBuilder($this, $name, $options, $parent);
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilderForProperty($class, $property, $data = null, array $options = array(), FormBuilderInterface $parent = null)
    {
        $guesser = $this->registry->getTypeGuesser();
        $typeGuess = $guesser->guessType($class, $property);
        $maxLengthGuess = $guesser->guessMaxLength($class, $property);
        // Keep $minLengthGuess for BC until Symfony 2.3
        $minLengthGuess = $guesser->guessMinLength($class, $property);
        $requiredGuess = $guesser->guessRequired($class, $property);
        $patternGuess = $guesser->guessPattern($class, $property);

        $type = $typeGuess ? $typeGuess->getType() : 'text';

        $maxLength = $maxLengthGuess ? $maxLengthGuess->getValue() : null;
        $minLength = $minLengthGuess ? $minLengthGuess->getValue() : null;
        $pattern   = $patternGuess ? $patternGuess->getValue() : null;

        // overrides $minLength, if set
        if (null !== $pattern) {
            $options = array_merge(array('pattern' => $pattern), $options);
        }

        if (null !== $maxLength) {
            $options = array_merge(array('max_length' => $maxLength), $options);
        }

        if (null !== $minLength && $minLength > 0) {
            $options = array_merge(array('pattern' => '.{'.$minLength.','.$maxLength.'}'), $options);
        }

        if ($requiredGuess) {
            $options = array_merge(array('required' => $requiredGuess->getValue()), $options);
        }

        // user options may override guessed options
        if ($typeGuess) {
            $options = array_merge($typeGuess->getOptions(), $options);
        }

        return $this->createNamedBuilder($property, $type, $data, $options, $parent);
    }

    /**
     * Returns whether the given type is supported.
     *
     * @param string $name The name of the type
     *
     * @return Boolean Whether the type is supported
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link FormRegistryInterface::hasType()} instead.
     */
    public function hasType($name)
    {
        return $this->registry->hasType($name);
    }

    /**
     * Adds a type.
     *
     * @param FormTypeInterface $type The type
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link FormRegistryInterface::resolveType()} and
     *             {@link FormRegistryInterface::addType()} instead.
     */
    public function addType(FormTypeInterface $type)
    {
        $this->registry->addType($this->registry->resolveType($type));
    }

    /**
     * Returns a type by name.
     *
     * This methods registers the type extensions from the form extensions.
     *
     * @param string $name The name of the type
     *
     * @return FormTypeInterface The type
     *
     * @throws Exception\FormException if the type can not be retrieved from any extension
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link FormRegistryInterface::getType()} instead.
     */
    public function getType($name)
    {
        return $this->registry->getType($name)->getInnerType();
    }
}
