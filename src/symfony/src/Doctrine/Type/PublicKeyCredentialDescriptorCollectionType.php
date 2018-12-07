<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2018 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Webauthn\Bundle\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Webauthn\PublicKeyCredentialDescriptorCollection;

final class PublicKeyCredentialDescriptorCollectionType extends Type
{
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return \Safe\json_encode($value);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        $json = \Safe\json_decode($value, true);

        return PublicKeyCredentialDescriptorCollection::createFromJson($json);
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return $platform->getJsonTypeDeclarationSQL($fieldDeclaration);
    }

    public function getName()
    {
        return 'public_key_credential_descriptor';
    }
}
