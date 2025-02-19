<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2019 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Webauthn\Bundle\DependencyInjection;

use Cose\Algorithm\Algorithm;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Webauthn\AttestationStatement\AttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputChecker;
use Webauthn\Bundle\DependencyInjection\Compiler\AttestationStatementSupportCompilerPass;
use Webauthn\Bundle\DependencyInjection\Compiler\CoseAlgorithmCompilerPass;
use Webauthn\Bundle\DependencyInjection\Compiler\DynamicRouteCompilerPass;
use Webauthn\Bundle\DependencyInjection\Compiler\ExtensionOutputCheckerCompilerPass;
use Webauthn\Bundle\DependencyInjection\Compiler\MetadataServiceCompilerPass;
use Webauthn\Bundle\DependencyInjection\Compiler\SingleMetadataCompilerPass;
use Webauthn\Bundle\Doctrine\Type as DbalType;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepository;
use Webauthn\ConformanceToolset\Controller\AssertionRequestController;
use Webauthn\ConformanceToolset\Controller\AssertionResponseController;
use Webauthn\ConformanceToolset\Controller\AssertionResponseControllerFactory;
use Webauthn\ConformanceToolset\Controller\AttestationRequestController;
use Webauthn\ConformanceToolset\Controller\AttestationResponseController;
use Webauthn\ConformanceToolset\Controller\AttestationResponseControllerFactory;
use Webauthn\MetadataService\DistantSingleMetadata;
use Webauthn\MetadataService\DistantSingleMetadataFactory;
use Webauthn\MetadataService\MetadataService;
use Webauthn\MetadataService\MetadataServiceFactory;
use Webauthn\MetadataService\MetadataStatementRepository;
use Webauthn\MetadataService\SingleMetadata;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\TokenBinding\TokenBindingHandler;

final class WebauthnExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @var string
     */
    private $alias;

    public function __construct(string $alias)
    {
        $this->alias = $alias;
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $container->registerForAutoconfiguration(AttestationStatementSupport::class)->addTag(AttestationStatementSupportCompilerPass::TAG);
        $container->registerForAutoconfiguration(ExtensionOutputChecker::class)->addTag(ExtensionOutputCheckerCompilerPass::TAG);
        $container->registerForAutoconfiguration(Algorithm::class)->addTag(CoseAlgorithmCompilerPass::TAG);
        $container->registerForAutoconfiguration(MetadataService::class)->addTag(MetadataServiceCompilerPass::TAG);
        $container->registerForAutoconfiguration(DistantSingleMetadata::class)->addTag(SingleMetadataCompilerPass::TAG);

        $container->setAlias(PublicKeyCredentialSourceRepository::class, $config['credential_repository']);
        $container->setAlias(TokenBindingHandler::class, $config['token_binding_support_handler']);
        $container->setParameter('webauthn.creation_profiles', $config['creation_profiles']);
        $container->setParameter('webauthn.request_profiles', $config['request_profiles']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config/'));
        $loader->load('services.php');
        $loader->load('cose.php');
        $loader->load('security.php');

        $this->loadTransportBindingProfile($container, $loader, $config);
        $this->loadMetadataServices($container, $loader, $config);

        if (null !== $config['user_repository']) {
            $container->setAlias(PublicKeyCredentialUserEntityRepository::class, $config['user_repository']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration($this->alias);
    }

    public function loadTransportBindingProfile(ContainerBuilder $container, LoaderInterface $loader, array $config): void
    {
        if (!class_exists(AttestationRequestController::class)) {
            return;
        }

        $loader->load('transport_binding_profile.php');

        foreach ($config['transport_binding_profile']['creation'] as $name => $profileConfig) {
            $attestationRequestControllerId = sprintf('webauthn.controller.transport_binding_profile.creation.request.%s', $name);
            $attestationRequestController = new Definition(AttestationRequestController::class);
            $attestationRequestController->setFactory([new Reference(AttestationResponseControllerFactory::class), 'createAttestationRequestController']);
            $attestationRequestController->setArguments([
                null,
                null,
                $profileConfig['profile_name'],
                $profileConfig['session_parameter_name'],
            ]);
            $attestationRequestController->addTag(DynamicRouteCompilerPass::TAG, ['path' => $profileConfig['request_path'], 'host' => $profileConfig['host']]);
            $attestationRequestController->addTag('controller.service_arguments');
            $container->setDefinition($attestationRequestControllerId, $attestationRequestController);

            $attestationResponseControllerId = sprintf('webauthn.controller.transport_binding_profile.creation.response.%s', $name);
            $attestationResponseController = new Definition(AttestationResponseController::class);
            $attestationResponseController->setFactory([new Reference(AttestationResponseControllerFactory::class), 'createAttestationResponseController']);
            $attestationResponseController->setArguments([
                null,
                null,
                $profileConfig['session_parameter_name'],
            ]);
            $attestationResponseController->addTag(DynamicRouteCompilerPass::TAG, ['path' => $profileConfig['response_path'], 'host' => $profileConfig['host']]);
            $attestationResponseController->addTag('controller.service_arguments');
            $container->setDefinition($attestationResponseControllerId, $attestationResponseController);
        }

        foreach ($config['transport_binding_profile']['request'] as $name => $profileConfig) {
            $assertionRequestControllerId = sprintf('webauthn.controller.transport_binding_profile.request.request.%s', $name);
            $assertionRequestController = new Definition(AssertionRequestController::class);
            $assertionRequestController->setFactory([new Reference(AssertionResponseControllerFactory::class), 'createAssertionRequestController']);
            $assertionRequestController->setArguments([
                null,
                null,
                $profileConfig['profile_name'],
                $profileConfig['session_parameter_name'],
            ]);
            $assertionRequestController->addTag(DynamicRouteCompilerPass::TAG, ['path' => $profileConfig['request_path'], 'host' => $profileConfig['host']]);
            $assertionRequestController->addTag('controller.service_arguments');
            $container->setDefinition($assertionRequestControllerId, $assertionRequestController);

            $assertionResponseControllerId = sprintf('webauthn.controller.transport_binding_profile.request.response.%s', $name);
            $assertionResponseController = new Definition(AssertionResponseController::class);
            $assertionResponseController->setFactory([new Reference(AssertionResponseControllerFactory::class), 'createAssertionResponseController']);
            $assertionResponseController->setArguments([$profileConfig['session_parameter_name']]);
            $assertionResponseController->addTag(DynamicRouteCompilerPass::TAG, ['path' => $profileConfig['response_path'], 'host' => $profileConfig['host']]);
            $assertionResponseController->addTag('controller.service_arguments');
            $container->setDefinition($assertionResponseControllerId, $assertionResponseController);
        }
    }

    private function loadAndroidSafetyNet(ContainerBuilder $container, LoaderInterface $loader, array $config): void
    {
        $container->setAlias('webauthn.android_safetynet.http_client', $config['android_safetynet']['http_client']);
        $container->setParameter('webauthn.android_safetynet.api_key', $config['android_safetynet']['api_key']);
        $container->setParameter('webauthn.android_safetynet.leeway', $config['android_safetynet']['leeway']);
        $container->setParameter('webauthn.android_safetynet.max_age', $config['android_safetynet']['max_age']);
        $loader->load('android_safetynet.php');
    }

    private function loadMetadataServices(ContainerBuilder $container, LoaderInterface $loader, array $config): void
    {
        //INFO: in v2.1, all metadata statement supports are loaded.
        // Starting at v3.0, if the metadata service is not enabled, only none will be available as this service will become mandatory for:
        // - FIDO2 U2F
        // - Packed
        // - Android Key
        // - Android SafetyNet
        // - TPM
        $loader->load('metadata_statement_supports.php');
        $this->loadAndroidSafetyNet($container, $loader, $config);

        if (false === $config['metadata_service']['enabled'] || !class_exists(MetadataServiceFactory::class)) {
            return;
        }
        $container->setAlias(MetadataStatementRepository::class, $config['metadata_service']['repository']);
        $container->setAlias('webauthn.metadata_service.http_client', $config['metadata_service']['http_client']);
        $container->setAlias('webauthn.metadata_service.request_factory', $config['metadata_service']['request_factory']);
        $loader->load('metadata_service.php');

        foreach ($config['metadata_service']['services'] as $name => $statementConfig) {
            $metadataServiceId = sprintf('webauthn.metadata_service.service.%s', $name);
            $metadataService = new Definition(MetadataService::class);
            $metadataService->setFactory([new Reference(MetadataServiceFactory::class), 'create']);
            $metadataService->setArguments([
                $statementConfig['uri'],
                $statementConfig['additional_query_string_values'],
                $statementConfig['additional_headers'],
                $statementConfig['http_client'],
            ]);
            $metadataService->setPublic($statementConfig['is_public']);
            $metadataService->addTag(MetadataServiceCompilerPass::TAG);
            $container->setDefinition($metadataServiceId, $metadataService);
        }
        foreach ($config['metadata_service']['distant_single_statements'] as $name => $statementConfig) {
            $metadataServiceId = sprintf('webauthn.metadata_service.distant_single_statement.%s', $name);
            $metadataService = new Definition(DistantSingleMetadata::class);
            $metadataService->setFactory([new Reference(DistantSingleMetadataFactory::class), 'create']);
            $metadataService->setArguments([
                $statementConfig['uri'],
                $statementConfig['is_base_64'],
                $statementConfig['additional_headers'],
                $statementConfig['http_client'],
            ]);
            $metadataService->setPublic($statementConfig['is_public']);
            $metadataService->addTag(SingleMetadataCompilerPass::TAG);
            $container->setDefinition($metadataServiceId, $metadataService);
        }
        foreach ($config['metadata_service']['from_data'] as $name => $statementConfig) {
            $metadataServiceId = sprintf('webauthn.metadata_service.from_data.%s', $name);
            $metadataService = new Definition(SingleMetadata::class);
            $metadataService->setArguments([
                $statementConfig['data'],
                $statementConfig['is_base_64'],
            ]);
            $metadataService->setPublic($statementConfig['is_public']);
            $metadataService->addTag(SingleMetadataCompilerPass::TAG);
            $container->setDefinition($metadataServiceId, $metadataService);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!\is_array($bundles) || !\array_key_exists('DoctrineBundle', $bundles)) {
            return;
        }
        $configs = $container->getExtensionConfig('doctrine');
        if (0 === \count($configs)) {
            return;
        }
        $config = current($configs);
        if (!isset($config['dbal'])) {
            $config['dbal'] = [];
        }
        if (!isset($config['dbal']['types'])) {
            $config['dbal']['types'] = [];
        }
        $config['dbal']['types'] += [
            'attested_credential_data' => DbalType\AttestedCredentialDataType::class,
            'aaguid' => DbalType\AAGUIDDataType::class,
            'base64' => DbalType\Base64BinaryDataType::class,
            'public_key_credential_descriptor' => DbalType\PublicKeyCredentialDescriptorType::class,
            'public_key_credential_descriptor_collection' => DbalType\PublicKeyCredentialDescriptorCollectionType::class,
            'trust_path' => DbalType\TrustPathDataType::class,
        ];
        $container->prependExtensionConfig('doctrine', $config);
    }
}
