<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use MuckiLogPlugin\ChangeLog\ValueNormalizer;
use PHPUnit\Framework\TestCase;

class ValueNormalizerTest extends TestCase
{
    private ValueNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ValueNormalizer();
    }

    public function testBinaryIdBecomesLowercaseHex(): void
    {
        $binary = hex2bin('AB' . str_repeat('00', 7) . 'FF' . str_repeat('11', 7));
        static::assertSame('ab00000000000000ff11111111111111', $this->normalizer->normalize($binary));
    }

    public function testValidUtf8ControlCharBinaryBecomesHex(): void
    {
        $binary = "\x01" . str_repeat("\x02", 15);
        static::assertSame('01' . str_repeat('02', 15), $this->normalizer->normalize($binary));
    }

    public function testPrintableSixteenCharStringStaysString(): void
    {
        static::assertSame('ABCDEFGHIJKLMNOP', $this->normalizer->normalize('ABCDEFGHIJKLMNOP'));
    }

    public function testJsonObjectStringIsDecoded(): void
    {
        static::assertSame(['a' => 1], $this->normalizer->normalize('{"a":1}'));
    }

    public function testInvalidJsonAndPlainStringsStayStrings(): void
    {
        static::assertSame('{broken', $this->normalizer->normalize('{broken'));
        static::assertSame('123', $this->normalizer->normalize('123'));
        static::assertSame('hello', $this->normalizer->normalize('hello'));
    }

    public function testResourceBecomesContent(): void
    {
        $res = fopen('php://memory', 'rb+');
        fwrite($res, 'content');
        rewind($res);
        static::assertSame('content', $this->normalizer->normalize($res));
    }

    public function testDateTimeIsFormattedAsAtom(): void
    {
        $date = new \DateTimeImmutable('2026-01-02 03:04:05+00:00');
        static::assertSame($date->format(\DATE_ATOM), $this->normalizer->normalize($date));
    }

    public function testScalarsStayUnchanged(): void
    {
        static::assertSame(5, $this->normalizer->normalize(5));
        static::assertSame(1.5, $this->normalizer->normalize(1.5));
        static::assertTrue($this->normalizer->normalize(true));
        static::assertNull($this->normalizer->normalize(null));
    }

    public function testResultIsAlwaysJsonEncodable(): void
    {
        $values = [random_bytes(5) . "\xff", \NAN, new \stdClass(), ['x' => "\xB1\x31"], new \DateTime()];
        foreach ($values as $value) {
            static::assertIsString(json_encode($this->normalizer->normalize($value), \JSON_THROW_ON_ERROR));
        }
    }
}
