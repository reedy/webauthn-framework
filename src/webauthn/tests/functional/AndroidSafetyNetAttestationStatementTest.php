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

namespace Webauthn\Tests\Functional;

use Base64Url\Base64Url;
use Cose\Algorithms;
use Http\Mock\Client;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Webauthn\AttestedCredentialData;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorData;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @group functional
 * @group Fido2
 */
class AndroidSafetyNetAttestationStatementTest extends AbstractTestCase
{
    /**
     * @test
     */
    public function anAndroidSafetyNetAttestationCanBeVerified(): void
    {
        $publicKeyCredentialCreationOptions = new PublicKeyCredentialCreationOptions(
            new PublicKeyCredentialRpEntity('My Application'),
            new PublicKeyCredentialUserEntity('test@foo.com', random_bytes(64), 'Test PublicKeyCredentialUserEntity'),
            base64_decode('kmns43CWVswbMovrKPkgd1lEpc6LZdfk0UQ/nuZbp00jW5C61PEW1dNaptZ0GkrIK9WRtaAXWkndIEEBgNICRw', true),
            [
                new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_ES256),
            ],
            60000,
            [],
            new AuthenticatorSelectionCriteria(),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_DIRECT,
            new AuthenticationExtensionsClientInputs()
        );

        $publicKeyCredential = $this->getPublicKeyCredentialLoader()->load('{"id":"Ac8zKrpVWv9UCwxY1FyMqkESz2lV4CNwTk2-Hp19LgKbvh5uQ2_i6AMbTbTz1zcNapCEeiLJPlAAVM4L7AIow6I","type":"public-key","rawId":"Ac8zKrpVWv9UCwxY1FyMqkESz2lV4CNwTk2+Hp19LgKbvh5uQ2/i6AMbTbTz1zcNapCEeiLJPlAAVM4L7AIow6I=","response":{"clientDataJSON":"eyJ0eXBlIjoid2ViYXV0aG4uY3JlYXRlIiwiY2hhbGxlbmdlIjoia21uczQzQ1dWc3diTW92cktQa2dkMWxFcGM2TFpkZmswVVFfbnVaYnAwMGpXNUM2MVBFVzFkTmFwdFowR2tySUs5V1J0YUFYV2tuZElFRUJnTklDUnciLCJvcmlnaW4iOiJodHRwczpcL1wvd2ViYXV0aG4ubW9yc2VsbGkuZnIiLCJhbmRyb2lkUGFja2FnZU5hbWUiOiJjb20uYW5kcm9pZC5jaHJvbWUifQ==","attestationObject":"o2NmbXRxYW5kcm9pZC1zYWZldHluZXRnYXR0U3RtdKJjdmVyaDE0Nzk5MDM3aHJlc3BvbnNlWRS9ZXlKaGJHY2lPaUpTVXpJMU5pSXNJbmcxWXlJNld5Sk5TVWxHYTJwRFEwSkljV2RCZDBsQ1FXZEpVVkpZY205T01GcFBaRkpyUWtGQlFVRkJRVkIxYm5wQlRrSm5hM0ZvYTJsSE9YY3dRa0ZSYzBaQlJFSkRUVkZ6ZDBOUldVUldVVkZIUlhkS1ZsVjZSV1ZOUW5kSFFURlZSVU5vVFZaU01qbDJXako0YkVsR1VubGtXRTR3U1VaT2JHTnVXbkJaTWxaNlRWSk5kMFZSV1VSV1VWRkVSWGR3U0ZaR1RXZFJNRVZuVFZVNGVFMUNORmhFVkVVMFRWUkJlRTFFUVROTlZHc3dUbFp2V0VSVVJUVk5WRUYzVDFSQk0wMVVhekJPVm05M1lrUkZURTFCYTBkQk1WVkZRbWhOUTFaV1RYaEZla0ZTUW1kT1ZrSkJaMVJEYTA1b1lrZHNiV0l6U25WaFYwVjRSbXBCVlVKblRsWkNRV05VUkZVeGRtUlhOVEJaVjJ4MVNVWmFjRnBZWTNoRmVrRlNRbWRPVmtKQmIxUkRhMlIyWWpKa2MxcFRRazFVUlUxNFIzcEJXa0puVGxaQ1FVMVVSVzFHTUdSSFZucGtRelZvWW0xU2VXSXliR3RNYlU1MllsUkRRMEZUU1hkRVVWbEtTMjlhU1doMlkwNUJVVVZDUWxGQlJHZG5SVkJCUkVORFFWRnZRMmRuUlVKQlRtcFlhM293WlVzeFUwVTBiU3N2UnpWM1QyOHJXRWRUUlVOeWNXUnVPRGh6UTNCU04yWnpNVFJtU3pCU2FETmFRMWxhVEVaSWNVSnJOa0Z0V2xaM01rczVSa2N3VHpseVVsQmxVVVJKVmxKNVJUTXdVWFZ1VXpsMVowaEROR1ZuT1c5MmRrOXRLMUZrV2pKd09UTllhSHAxYmxGRmFGVlhXRU40UVVSSlJVZEtTek5UTW1GQlpucGxPVGxRVEZNeU9XaE1ZMUYxV1ZoSVJHRkROMDlhY1U1dWIzTnBUMGRwWm5NNGRqRnFhVFpJTDNob2JIUkRXbVV5YkVvck4wZDFkSHBsZUV0d2VIWndSUzkwV2xObVlsazVNRFZ4VTJ4Q2FEbG1jR293TVRWamFtNVJSbXRWYzBGVmQyMUxWa0ZWZFdWVmVqUjBTMk5HU3pSd1pYWk9UR0Y0UlVGc0swOXJhV3hOZEVsWlJHRmpSRFZ1Wld3MGVFcHBlWE0wTVROb1lXZHhWekJYYUdnMVJsQXpPV2hIYXpsRkwwSjNVVlJxWVhwVGVFZGtkbGd3YlRaNFJsbG9hQzh5VmsxNVdtcFVORXQ2VUVwRlEwRjNSVUZCWVU5RFFXeG5kMmRuU2xWTlFUUkhRVEZWWkVSM1JVSXZkMUZGUVhkSlJtOUVRVlJDWjA1V1NGTlZSVVJFUVV0Q1oyZHlRbWRGUmtKUlkwUkJWRUZOUW1kT1ZraFNUVUpCWmpoRlFXcEJRVTFDTUVkQk1WVmtSR2RSVjBKQ1VYRkNVWGRIVjI5S1FtRXhiMVJMY1hWd2J6UlhObmhVTm1veVJFRm1RbWRPVmtoVFRVVkhSRUZYWjBKVFdUQm1hSFZGVDNaUWJTdDRaMjU0YVZGSE5rUnlabEZ1T1V0NlFtdENaMmR5UW1kRlJrSlJZMEpCVVZKWlRVWlpkMHAzV1VsTGQxbENRbEZWU0UxQlIwZEhNbWd3WkVoQk5reDVPWFpaTTA1M1RHNUNjbUZUTlc1aU1qbHVUREprTUdONlJuWk5WRUZ5UW1kbmNrSm5SVVpDVVdOM1FXOVpabUZJVWpCalJHOTJURE5DY21GVE5XNWlNamx1VERKa2VtTnFTWFpTTVZKVVRWVTRlRXh0VG5sa1JFRmtRbWRPVmtoU1JVVkdha0ZWWjJoS2FHUklVbXhqTTFGMVdWYzFhMk50T1hCYVF6VnFZakl3ZDBsUldVUldVakJuUWtKdmQwZEVRVWxDWjFwdVoxRjNRa0ZuU1hkRVFWbExTM2RaUWtKQlNGZGxVVWxHUVhwQmRrSm5UbFpJVWpoRlMwUkJiVTFEVTJkSmNVRm5hR2cxYjJSSVVuZFBhVGgyV1ROS2MweHVRbkpoVXpWdVlqSTVia3d3WkZWVmVrWlFUVk0xYW1OdGQzZG5aMFZGUW1kdmNrSm5SVVZCWkZvMVFXZFJRMEpKU0RGQ1NVaDVRVkJCUVdSM1EydDFVVzFSZEVKb1dVWkpaVGRGTmt4TldqTkJTMUJFVjFsQ1VHdGlNemRxYW1RNE1FOTVRVE5qUlVGQlFVRlhXbVJFTTFCTVFVRkJSVUYzUWtsTlJWbERTVkZEVTFwRFYyVk1Tblp6YVZaWE5rTm5LMmRxTHpsM1dWUktVbnAxTkVocGNXVTBaVmswWXk5dGVYcHFaMGxvUVV4VFlta3ZWR2g2WTNweGRHbHFNMlJyTTNaaVRHTkpWek5NYkRKQ01HODNOVWRSWkdoTmFXZGlRbWRCU0ZWQlZtaFJSMjFwTDFoM2RYcFVPV1ZIT1ZKTVNTdDRNRm95ZFdKNVdrVldla0UzTlZOWlZtUmhTakJPTUVGQlFVWnRXRkU1ZWpWQlFVRkNRVTFCVW1wQ1JVRnBRbU5EZDBFNWFqZE9WRWRZVURJM09IbzBhSEl2ZFVOSWFVRkdUSGx2UTNFeVN6QXJlVXhTZDBwVlltZEpaMlk0WjBocWRuQjNNbTFDTVVWVGFuRXlUMll6UVRCQlJVRjNRMnR1UTJGRlMwWlZlVm8zWmk5UmRFbDNSRkZaU2t0dldrbG9kbU5PUVZGRlRFSlJRVVJuWjBWQ1FVazVibFJtVWt0SlYyZDBiRmRzTTNkQ1REVTFSVlJXTm10aGVuTndhRmN4ZVVGak5VUjFiVFpZVHpReGExcDZkMG8yTVhkS2JXUlNVbFF2VlhORFNYa3hTMFYwTW1Nd1JXcG5iRzVLUTBZeVpXRjNZMFZYYkV4UldUSllVRXg1Um1wclYxRk9ZbE5vUWpGcE5GY3lUbEpIZWxCb2RETnRNV0kwT1doaWMzUjFXRTAyZEZnMVEzbEZTRzVVYURoQ2IyMDBMMWRzUm1sb2VtaG5iamd4Ukd4a2IyZDZMMHN5VlhkTk5sTTJRMEl2VTBWNGEybFdabllyZW1KS01ISnFkbWM1TkVGc1pHcFZabFYzYTBrNVZrNU5ha1ZRTldVNGVXUkNNMjlNYkRabmJIQkRaVVkxWkdkbVUxZzBWVGw0TXpWdmFpOUpTV1F6VlVVdlpGQndZaTl4WjBkMmMydG1aR1Y2ZEcxVmRHVXZTMU50Y21sM1kyZFZWMWRsV0daVVlra3plbk5wYTNkYVltdHdiVkpaUzIxcVVHMW9kalJ5YkdsNlIwTkhkRGhRYmpod2NUaE5Na3RFWmk5UU0ydFdiM1F6WlRFNFVUMGlMQ0pOU1VsRlUycERRMEY2UzJkQmQwbENRV2RKVGtGbFR6QnRjVWRPYVhGdFFrcFhiRkYxUkVGT1FtZHJjV2hyYVVjNWR6QkNRVkZ6UmtGRVFrMU5VMEYzU0dkWlJGWlJVVXhGZUdSSVlrYzVhVmxYZUZSaFYyUjFTVVpLZG1JelVXZFJNRVZuVEZOQ1UwMXFSVlJOUWtWSFFURlZSVU5vVFV0U01uaDJXVzFHYzFVeWJHNWlha1ZVVFVKRlIwRXhWVVZCZUUxTFVqSjRkbGx0Um5OVk1teHVZbXBCWlVaM01IaE9la0V5VFZSVmQwMUVRWGRPUkVwaFJuY3dlVTFVUlhsTlZGVjNUVVJCZDA1RVNtRk5SVWw0UTNwQlNrSm5UbFpDUVZsVVFXeFdWRTFTTkhkSVFWbEVWbEZSUzBWNFZraGlNamx1WWtkVloxWklTakZqTTFGblZUSldlV1J0YkdwYVdFMTRSWHBCVWtKblRsWkNRVTFVUTJ0a1ZWVjVRa1JSVTBGNFZIcEZkMmRuUldsTlFUQkhRMU54UjFOSllqTkVVVVZDUVZGVlFVRTBTVUpFZDBGM1oyZEZTMEZ2U1VKQlVVUlJSMDA1UmpGSmRrNHdOWHByVVU4NUszUk9NWEJKVW5aS2VucDVUMVJJVnpWRWVrVmFhRVF5WlZCRGJuWlZRVEJSYXpJNFJtZEpRMlpMY1VNNVJXdHpRelJVTW1aWFFsbHJMMnBEWmtNelVqTldXazFrVXk5a1RqUmFTME5GVUZwU2NrRjZSSE5wUzFWRWVsSnliVUpDU2pWM2RXUm5lbTVrU1UxWlkweGxMMUpIUjBac05YbFBSRWxMWjJwRmRpOVRTa2d2VlV3clpFVmhiSFJPTVRGQ2JYTkxLMlZSYlUxR0t5dEJZM2hIVG1oeU5UbHhUUzg1YVd3M01Va3laRTQ0UmtkbVkyUmtkM1ZoWldvMFlsaG9jREJNWTFGQ1ltcDRUV05KTjBwUU1HRk5NMVEwU1N0RWMyRjRiVXRHYzJKcWVtRlVUa001ZFhwd1JteG5UMGxuTjNKU01qVjRiM2x1VlhoMk9IWk9iV3R4TjNwa1VFZElXR3Q0VjFrM2IwYzVhaXRLYTFKNVFrRkNhemRZY2twbWIzVmpRbHBGY1VaS1NsTlFhemRZUVRCTVMxY3dXVE42Tlc5Nk1rUXdZekYwU2t0M1NFRm5UVUpCUVVkcVoyZEZlazFKU1VKTWVrRlBRbWRPVmtoUk9FSkJaamhGUWtGTlEwRlpXWGRJVVZsRVZsSXdiRUpDV1hkR1FWbEpTM2RaUWtKUlZVaEJkMFZIUTBOelIwRlJWVVpDZDAxRFRVSkpSMEV4VldSRmQwVkNMM2RSU1UxQldVSkJaamhEUVZGQmQwaFJXVVJXVWpCUFFrSlpSVVpLYWxJclJ6UlJOamdyWWpkSFEyWkhTa0ZpYjA5ME9VTm1NSEpOUWpoSFFURlZaRWwzVVZsTlFtRkJSa3AyYVVJeFpHNUlRamRCWVdkaVpWZGlVMkZNWkM5alIxbFpkVTFFVlVkRFEzTkhRVkZWUmtKM1JVSkNRMnQzU25wQmJFSm5aM0pDWjBWR1FsRmpkMEZaV1ZwaFNGSXdZMFJ2ZGt3eU9XcGpNMEYxWTBkMGNFeHRaSFppTW1OMldqTk9lVTFxUVhsQ1owNVdTRkk0UlV0NlFYQk5RMlZuU21GQmFtaHBSbTlrU0ZKM1QyazRkbGt6U25OTWJrSnlZVk0xYm1JeU9XNU1NbVI2WTJwSmRsb3pUbmxOYVRWcVkyMTNkMUIzV1VSV1VqQm5Ra1JuZDA1cVFUQkNaMXB1WjFGM1FrRm5TWGRMYWtGdlFtZG5ja0puUlVaQ1VXTkRRVkpaWTJGSVVqQmpTRTAyVEhrNWQyRXlhM1ZhTWpsMlduazVlVnBZUW5aak1td3dZak5LTlV4NlFVNUNaMnR4YUd0cFJ6bDNNRUpCVVhOR1FVRlBRMEZSUlVGSGIwRXJUbTV1TnpoNU5uQlNhbVE1V0d4UlYwNWhOMGhVWjJsYUwzSXpVazVIYTIxVmJWbElVRkZ4TmxOamRHazVVRVZoYW5aM1VsUXlhVmRVU0ZGeU1ESm1aWE54VDNGQ1dUSkZWRlYzWjFwUksyeHNkRzlPUm5ab2MwODVkSFpDUTA5SllYcHdjM2RYUXpsaFNqbDRhblUwZEZkRVVVZzRUbFpWTmxsYVdpOVlkR1ZFVTBkVk9WbDZTbkZRYWxrNGNUTk5SSGh5ZW0xeFpYQkNRMlkxYnpodGR5OTNTalJoTWtjMmVIcFZjalpHWWpaVU9FMWpSRTh5TWxCTVVrdzJkVE5OTkZSNmN6TkJNazB4YWpaaWVXdEtXV2s0ZDFkSlVtUkJka3RNVjFwMUwyRjRRbFppZWxsdGNXMTNhMjAxZWt4VFJGYzFia2xCU21KRlRFTlJRMXAzVFVnMU5uUXlSSFp4YjJaNGN6WkNRbU5EUmtsYVZWTndlSFUyZURaMFpEQldOMU4yU2tORGIzTnBjbE50U1dGMGFpODVaRk5UVmtSUmFXSmxkRGh4THpkVlN6UjJORnBWVGpnd1lYUnVXbm94ZVdjOVBTSmRmUS5leUp1YjI1alpTSTZJbVoxUlZsb0szaFhVRkEzZUhCNVVUZzVhbGh3Y0ZGT05tMWlNV2RYWnpNM1JsQnZOM05VU2pFeFVFMDlJaXdpZEdsdFpYTjBZVzF3VFhNaU9qRTFORGcwT0RneU5UazRNamtzSW1Gd2ExQmhZMnRoWjJWT1lXMWxJam9pWTI5dExtZHZiMmRzWlM1aGJtUnliMmxrTG1kdGN5SXNJbUZ3YTBScFoyVnpkRk5vWVRJMU5pSTZJa0YyV0hGcE1FSnRiVXRKYm1KSVlqTXlaalI2VldoMmVqUmxjR3BwU25RM2EwdE5SMmhUZDNjeFJGVTlJaXdpWTNSelVISnZabWxzWlUxaGRHTm9JanAwY25WbExDSmhjR3REWlhKMGFXWnBZMkYwWlVScFoyVnpkRk5vWVRJMU5pSTZXeUk0VURGelZ6QkZVRXBqYzJ4M04xVjZVbk5wV0V3Mk5IY3JUelV3UldRclVrSkpRM1JoZVRGbk1qUk5QU0pkTENKaVlYTnBZMGx1ZEdWbmNtbDBlU0k2ZEhKMVpYMC5DQldQQ1FNaDBIdjhSTllZc05HTGVuci16RVEyY3o2Q25xalZhblZKOXV1b0d5WFpkc19mRTkwbFRjN0tpYVFMNExWSDl1TnNLWjdyN0xZSzRHTHhHekNqWklwZFlFZUIwdWxaWEN1bDdaVFI2MzZmODBWZmxkZ0dJdDRocWJ6S3dsd0EwNEZJN3ZpbDZjbkNJRHQ4SHVyTzVwRnJIdDVhUkpVcUxnOWhPT3VOaDVYS1JQS29aVTZyQlg5eVhxUmFtbl9SbWd6SkEwRGpqcXNaM3BlYUVvX2g5T0hJUHV3Q3FXZUdlZk5lRmoxVnBnaENpdW1lMXpPb2lwSmt3Tkx3dHdJamNDZ0VqYmc1OEF6ZHBPY01fLUtKYXBUeFJlYk9ZclM3dExTUlZfb2xjZG9PWGUtZ0ctVktCeTRUclJkdE9zNUdydTBqdlNyUGMwZXh6OHV2MkFoYXV0aERhdGFYxcrUbtuZYVMj5mIkvf6KvF1ZzC0gYwKd4+myQgSJCUO2RQAAAAC5P9lh8uZGL7EiggAiR954AEEBzzMqulVa/1QLDFjUXIyqQRLPaVXgI3BOTb4enX0uApu+Hm5Db+LoAxtNtPPXNw1qkIR6Isk+UABUzgvsAijDoqUBAgMmIAEhWCACJyweJ5aGUeFWycOhX/jCeAcTVjAxnbZnJmxj+aLWtyJYIAOY6jc/2y5iT60VYTtZaeBvsQIwgU/XR9Fax7xtatkY"}}');

        static::assertInstanceOf(AuthenticatorAttestationResponse::class, $publicKeyCredential->getResponse());

        $credentialRepository = $this->prophesize(PublicKeyCredentialSourceRepository::class);
        $credentialRepository->findOneByCredentialId(base64_decode('Ac8zKrpVWv9UCwxY1FyMqkESz2lV4CNwTk2+Hp19LgKbvh5uQ2/i6AMbTbTz1zcNapCEeiLJPlAAVM4L7AIow6I=', true))->willReturn(null);

        $uri = $this->prophesize(UriInterface::class);
        $uri->getHost()->willReturn('webauthn.morselli.fr');
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri->reveal());

        $client = new Client();
        $httpResponse = new Response();
        $httpResponse = $httpResponse->withHeader('content-type', 'application/json');
        $httpResponse->getBody()->write('{"isValidSignature":true}');
        $httpResponse->getBody()->rewind();
        $client->setDefaultResponse($httpResponse);

        $this->getAuthenticatorAttestationResponseValidator($credentialRepository->reveal(), $client)->check(
            $publicKeyCredential->getResponse(),
            $publicKeyCredentialCreationOptions,
            $request->reveal()
        );

        $publicKeyCredentialDescriptor = $publicKeyCredential->getPublicKeyCredentialDescriptor(['usb']);

        static::assertEquals(base64_decode('Ac8zKrpVWv9UCwxY1FyMqkESz2lV4CNwTk2+Hp19LgKbvh5uQ2/i6AMbTbTz1zcNapCEeiLJPlAAVM4L7AIow6I=', true), Base64Url::decode($publicKeyCredential->getId()));
        static::assertEquals(base64_decode('Ac8zKrpVWv9UCwxY1FyMqkESz2lV4CNwTk2+Hp19LgKbvh5uQ2/i6AMbTbTz1zcNapCEeiLJPlAAVM4L7AIow6I=', true), $publicKeyCredentialDescriptor->getId());
        static::assertEquals(PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, $publicKeyCredentialDescriptor->getType());
        static::assertEquals(['usb'], $publicKeyCredentialDescriptor->getTransports());

        /** @var AuthenticatorData $authenticatorData */
        $authenticatorData = $publicKeyCredential->getResponse()->getAttestationObject()->getAuthData();

        static::assertEquals(hex2bin('cad46edb99615323e66224bdfe8abc5d59cc2d2063029de3e9b24204890943b6'), $authenticatorData->getRpIdHash());
        static::assertTrue($authenticatorData->isUserPresent());
        static::assertTrue($authenticatorData->isUserVerified());
        static::assertTrue($authenticatorData->hasAttestedCredentialData());
        static::assertEquals(0, $authenticatorData->getReservedForFutureUse1());
        static::assertEquals(0, $authenticatorData->getReservedForFutureUse2());
        static::assertEquals(0, $authenticatorData->getSignCount());
        static::assertInstanceOf(AttestedCredentialData::class, $authenticatorData->getAttestedCredentialData());
        static::assertFalse($authenticatorData->hasExtensions());
    }

    /**
     * @test
     */
    public function debug(): void
    {
        $publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::createFromString('{"status":"ok","errorMessage":"","rp":{"name":"Webauthn Demo","id":"webauthn.spomky-labs.com"},"pubKeyCredParams":[{"type":"public-key","alg":-8},{"type":"public-key","alg":-7},{"type":"public-key","alg":-43},{"type":"public-key","alg":-35},{"type":"public-key","alg":-36},{"type":"public-key","alg":-257},{"type":"public-key","alg":-258},{"type":"public-key","alg":-259},{"type":"public-key","alg":-37},{"type":"public-key","alg":-38},{"type":"public-key","alg":-39}],"challenge":"RYUukgzjxdP9LrN6V0nw9JB6qYiWPL-tmVEiPyp-TlQ","attestation":"direct","user":{"name":"3nSFW9aXgZ6-R_i-eZ36","id":"YzMwZDhjNzMtOWUwZi00OGQ4LTlmNTgtOTVhN2Y4NjI1NzUy","displayName":"Stanford Mcguffie"},"authenticatorSelection":{"requireResidentKey":false,"userVerification":"preferred"},"timeout":60000}');

        $publicKeyCredential = $this->getPublicKeyCredentialLoader()->load('{"id":"etUX3yDyIhSoxjifoTP6IlbHn0j-ERe9LOwDgbmjSKQ","rawId":"etUX3yDyIhSoxjifoTP6IlbHn0j-ERe9LOwDgbmjSKQ","response":{"attestationObject":"o2NmbXRmcGFja2VkZ2F0dFN0bXSjY2FsZyZjc2lnWEYwRAIgDWExWr_Lc-oyadVt9auyGk4j9HX9Gr4mbWxGL7P31soCICC6Pr_4WvMhg0nrUN4TeLp_yna9IUS2AoojuU16VEyzY3g1Y4JZApIwggKOMIICNKADAgECAgEBMAoGCCqGSM49BAMCMIGvMSYwJAYDVQQDDB1GSURPMiBJTlRFUk1FRElBVEUgcHJpbWUyNTZ2MTExMC8GCSqGSIb3DQEJARYiY29uZm9ybWFuY2UtdG9vbHNAZmlkb2FsbGlhbmNlLm9yZzEWMBQGA1UECgwNRklETyBBbGxpYW5jZTEMMAoGA1UECwwDQ1dHMQswCQYDVQQGEwJVUzELMAkGA1UECAwCTVkxEjAQBgNVBAcMCVdha2VmaWVsZDAeFw0xODA1MjMxNDM3NDFaFw0yODA1MjAxNDM3NDFaMIHCMSMwIQYDVQQDDBpGSURPMiBCQVRDSCBLRVkgcHJpbWUyNTZ2MTExMC8GCSqGSIb3DQEJARYiY29uZm9ybWFuY2UtdG9vbHNAZmlkb2FsbGlhbmNlLm9yZzEWMBQGA1UECgwNRklETyBBbGxpYW5jZTEiMCAGA1UECwwZQXV0aGVudGljYXRvciBBdHRlc3RhdGlvbjELMAkGA1UEBhMCVVMxCzAJBgNVBAgMAk1ZMRIwEAYDVQQHDAlXYWtlZmllbGQwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAAS62WKs8a1SL5r7CgySHBfb6zqZk9Cko2ZjvsT1y_Ia-4BkaWUcUZGD9HyRD8wqNGUytw_rxxbbHQ4BvVduKUhkoywwKjAJBgNVHRMEAjAAMB0GA1UdDgQWBBRKVOUG0pFET20PM13W_cdGbLlfVDAKBggqhkjOPQQDAgNIADBFAiEAuVs2tHN9o6wQ0w2-9euV5QQnQlpElX874Yah038n6kUCIE0LrMA4QKR8Kk_1-lvGmHHKnpym_RyhfAzWkYRzNyl4WQQ1MIIEMTCCAhmgAwIBAgIBAjANBgkqhkiG9w0BAQsFADCBoTEYMBYGA1UEAwwPRklETzIgVEVTVCBST09UMTEwLwYJKoZIhvcNAQkBFiJjb25mb3JtYW5jZS10b29sc0BmaWRvYWxsaWFuY2Uub3JnMRYwFAYDVQQKDA1GSURPIEFsbGlhbmNlMQwwCgYDVQQLDANDV0cxCzAJBgNVBAYTAlVTMQswCQYDVQQIDAJNWTESMBAGA1UEBwwJV2FrZWZpZWxkMB4XDTE4MDcyMzE0MjkwN1oXDTQ1MTIwODE0MjkwN1owga8xJjAkBgNVBAMMHUZJRE8yIElOVEVSTUVESUFURSBwcmltZTI1NnYxMTEwLwYJKoZIhvcNAQkBFiJjb25mb3JtYW5jZS10b29sc0BmaWRvYWxsaWFuY2Uub3JnMRYwFAYDVQQKDA1GSURPIEFsbGlhbmNlMQwwCgYDVQQLDANDV0cxCzAJBgNVBAYTAlVTMQswCQYDVQQIDAJNWTESMBAGA1UEBwwJV2FrZWZpZWxkMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE3EEd31Vtf01zjKl1FHpVcfRjACkj1m-0e16XhyBwWQUOyc6HqW80V4rU3Qi0wnzV6SxNRuiUXLLT5l_bS0l586MvMC0wDAYDVR0TBAUwAwEB_zAdBgNVHQ4EFgQUZ8EZkpBb9V0AwdYhDowtdiGSR8AwDQYJKoZIhvcNAQELBQADggIBAAuYLqezVLsHJ3Yn7-XcJWehiEj0xHIkHTMZjaPZxPQUQDtm7UNA3SXYnwPBp0EHdvXSIJPFOPFrnGOKs7r0WWius-gCK1NmwFJSayp5U2b43YjN6Ik2PVk4gfXsn5iv2YBL-1vQxBVu764d9vqY0jRdVcsbjBKZ2tV-rMqrTQ1RvsNGC43ZF4tHIrkSctEPQPdL5jCoAMYJ0XwqJeWkFJR6WTE4ivvDgqfLEqKtOUDd_Yst-LuAHihlFnrio2BMDbICoJ_r9fgNXW1MNnFmIOdzouZvw0C5bflrNYaJLsF8QnpGgb4ngfZ7td32F7-0pIMLljzcMhT5UJFqSD4G_XmTBN5J1IidhAEtVBO5K2ljYN3EDtr-rWNuPufhZhMrlopxgoax7ME9LGLZoUBpVmtGwlfXxCy-vWwjuuEYlqHpy7Il9eYZpgu_mWxfQ9VR49QR0fXoqAGVFaJxIgyUmR7VcV5ZlN40AYaxD87ReUZ-u9Hc6vxOByz3826ylvi9hdovlhFhe3LYnDVQQS11B7BQLxmDKr-wxNMwxmmey_o1yI0gohNiI4sQoTGMP2hWMJsdDesrl3iQ2LvHwklzikz0emUbCwkN_LVxUkEcp9U-RYL8XbO0NrMYLVVwjcvBTKKH9u4IzLuYuKQLdpXVxDsdcyNj_jb-hhcWNlPwbVyDaGF1dGhEYXRhWKSWBOqCgk6YpK2hS0Ri0Nc6jsRpEw2pGxkwdFkin3SjWUEAAABagPU9HoUuQ-27P9AvEyLlrwAgetUX3yDyIhSoxjifoTP6IlbHn0j-ERe9LOwDgbmjSKSlAQIDJiABIVgg1ry9E50CeEzBcZHzZ9KzIAtFG6lSe_OrL4LyCMhJYskiWCBXeXEAWodT2qsda6uNiQ6QsZCZnJdEpUq45SlzQkcg_w","clientDataJSON":"eyJvcmlnaW4iOiJodHRwczovL3dlYmF1dGhuLnNwb21reS1sYWJzLmNvbSIsImNoYWxsZW5nZSI6IlJZVXVrZ3pqeGRQOUxyTjZWMG53OUpCNnFZaVdQTC10bVZFaVB5cC1UbFEiLCJ0eXBlIjoid2ViYXV0aG4uY3JlYXRlIn0"},"type":"public-key"}');

        static::assertInstanceOf(AuthenticatorAttestationResponse::class, $publicKeyCredential->getResponse());

        $credentialRepository = $this->prophesize(PublicKeyCredentialSourceRepository::class);
        $credentialRepository->findOneByCredentialId(base64_decode('Ac8zKrpVWv9UCwxY1FyMqkESz2lV4CNwTk2+Hp19LgKbvh5uQ2/i6AMbTbTz1zcNapCEeiLJPlAAVM4L7AIow6I=', true))->willReturn(null);

        $uri = $this->prophesize(UriInterface::class);
        $uri->getHost()->willReturn('webauthn.morselli.fr');
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri->reveal());

        $client = new Client();
        $httpResponse = new Response();
        $httpResponse = $httpResponse->withHeader('content-type', 'application/json');
        $httpResponse->getBody()->write('{"isValidSignature":true}');
        $httpResponse->getBody()->rewind();
        $client->setDefaultResponse($httpResponse);

        $this->getAuthenticatorAttestationResponseValidator($credentialRepository->reveal(), $client)->check(
            $publicKeyCredential->getResponse(),
            $publicKeyCredentialCreationOptions,
            $request->reveal()
        );

        $publicKeyCredentialDescriptor = $publicKeyCredential->getPublicKeyCredentialDescriptor(['usb']);

        static::assertEquals(base64_decode('Ac8zKrpVWv9UCwxY1FyMqkESz2lV4CNwTk2+Hp19LgKbvh5uQ2/i6AMbTbTz1zcNapCEeiLJPlAAVM4L7AIow6I=', true), Base64Url::decode($publicKeyCredential->getId()));
        static::assertEquals(base64_decode('Ac8zKrpVWv9UCwxY1FyMqkESz2lV4CNwTk2+Hp19LgKbvh5uQ2/i6AMbTbTz1zcNapCEeiLJPlAAVM4L7AIow6I=', true), $publicKeyCredentialDescriptor->getId());
        static::assertEquals(PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, $publicKeyCredentialDescriptor->getType());
        static::assertEquals(['usb'], $publicKeyCredentialDescriptor->getTransports());

        /** @var AuthenticatorData $authenticatorData */
        $authenticatorData = $publicKeyCredential->getResponse()->getAttestationObject()->getAuthData();

        static::assertEquals(hex2bin('cad46edb99615323e66224bdfe8abc5d59cc2d2063029de3e9b24204890943b6'), $authenticatorData->getRpIdHash());
        static::assertTrue($authenticatorData->isUserPresent());
        static::assertTrue($authenticatorData->isUserVerified());
        static::assertTrue($authenticatorData->hasAttestedCredentialData());
        static::assertEquals(0, $authenticatorData->getReservedForFutureUse1());
        static::assertEquals(0, $authenticatorData->getReservedForFutureUse2());
        static::assertEquals(0, $authenticatorData->getSignCount());
        static::assertInstanceOf(AttestedCredentialData::class, $authenticatorData->getAttestedCredentialData());
        static::assertFalse($authenticatorData->hasExtensions());
    }
}
