<?php

declare(strict_types=1);

use Sirix\Monolog\Redaction\Rule\EmailRule;
use Sirix\Monolog\Redaction\Rule\FixedValueRule;
use Sirix\Monolog\Redaction\Rule\FullMaskRule;
use Sirix\Monolog\Redaction\Rule\NameRule;
use Sirix\Monolog\Redaction\Rule\NullRule;
use Sirix\Monolog\Redaction\Rule\PhoneRule;
use Sirix\Monolog\Redaction\Rule\StartEndRule;

return [
    'card_number' => new StartEndRule(6, 4),
    'pan' => new StartEndRule(6, 4),
    'acctNumber' => new StartEndRule(6, 4),
    'customeraccountnumber' => new StartEndRule(6, 4),
    'destination' => new StartEndRule(6, 4),
    'cardNum' => new StartEndRule(6, 4),

    'security_code' => new FullMaskRule(),
    'cvv' => new FullMaskRule(),
    'securitycode' => new FullMaskRule(),
    'card_cvv' => new FullMaskRule(),
    'exp_month' => new FullMaskRule(),
    'exp_year' => new FullMaskRule(),
    'expiration_month' => new FullMaskRule(),
    'expiration_year' => new FullMaskRule(),
    'cardExpiryDate' => new FullMaskRule(),
    'acquirerBIN' => new FullMaskRule(),
    'ccExpMonth' => new FullMaskRule(),
    'ccExpYear' => new FullMaskRule(),
    'month' => new FullMaskRule(),
    'year' => new FullMaskRule(),

    'expirydate' => new FixedValueRule('**/****'),

    'cavv' => new FixedValueRule('*'),
    'threeddirectorytransactionreference' => new FixedValueRule('*'),
    'authenticationValue' => new FixedValueRule('*'),
    'dsTransID' => new FixedValueRule('*'),
    'sitereference' => new FixedValueRule('*'),
    'address' => new FixedValueRule('*'),
    'street' => new FixedValueRule('*'),
    'zip' => new FixedValueRule('*'),
    'ip' => new FixedValueRule('*'),
    'browser_ip' => new FixedValueRule('*'),
    'customerIp' => new FixedValueRule('*'),
    'password' => new FixedValueRule('*'),
    'auth' => new FixedValueRule('*'),
    'accessor' => new FixedValueRule('*'),
    'payload' => new FixedValueRule('*'),
    'paymentHandleToken' => new FixedValueRule('*'),
    'ciphertext' => new FixedValueRule('*'),
    'threeDSSessionData' => new FixedValueRule('*'),
    'creq' => new FixedValueRule('*'),
    'form3d_html' => new FixedValueRule('*'),
    'auth_code' => new FixedValueRule('*'),
    'dsReferenceNumber' => new FixedValueRule('*'),
    'Signature' => new FixedValueRule('*'),
    'Password' => new FixedValueRule('*'),
    'Username' => new FixedValueRule('*'),
    'IP' => new FixedValueRule('*'),
    'signature' => new FixedValueRule('*'),

    'pay_form_3d' => new NullRule(),
    'PaRes' => new NullRule(),
    'pares' => new NullRule(),
    'MD' => new NullRule(),
    'md' => new NullRule(),
    'form3d' => new NullRule(),
    'payment_url' => new NullRule(),
    'SuccessURL' => new NullRule(),
    'FailURL' => new NullRule(),

    'card_holder' => new NameRule(),
    'holder' => new NameRule(),
    'name' => new NameRule(),
    'customerfirstname' => new NameRule(),
    'customerlastname' => new NameRule(),
    'full_name' => new NameRule(),
    'wallet' => new NameRule(),
    'firstName' => new NameRule(),
    'lastName' => new NameRule(),
    'consumerId' => new NameRule(),
    'holderName' => new NameRule(),
    'Firstname' => new NameRule(),
    'Lastname' => new NameRule(),

    'phone' => new PhoneRule(),
    'MobilePhone' => new PhoneRule(),

    'email' => new EmailRule(),
    'client_email' => new EmailRule(),
    'customeremail' => new EmailRule(),
    'pay_from_email' => new EmailRule(),
    'pay_to_email' => new EmailRule(),
    'Email' => new EmailRule(),
];
