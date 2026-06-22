<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Helpers\Validator;

class ValidatorTest extends TestCase
{
    public function test_required_field_fails_when_empty(): void
    {
        $v = Validator::make(['email' => ''], ['email' => 'required|email']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('email', $v->errors());
    }

    public function test_valid_email_passes(): void
    {
        $v = Validator::make(['email' => 'user@example.com'], ['email' => 'required|email']);
        $this->assertFalse($v->fails());
    }

    public function test_invalid_email_fails(): void
    {
        $v = Validator::make(['email' => 'not-an-email'], ['email' => 'required|email']);
        $this->assertTrue($v->fails());
    }

    public function test_min_length_enforced(): void
    {
        $v = Validator::make(['password' => 'short'], ['password' => 'required|string|min:10']);
        $this->assertTrue($v->fails());
    }

    public function test_max_length_enforced(): void
    {
        $v = Validator::make(['name' => str_repeat('a', 201)], ['name' => 'required|string|max:200']);
        $this->assertTrue($v->fails());
    }

    public function test_uuid_validation(): void
    {
        $v1 = Validator::make(['id' => '550e8400-e29b-41d4-a716-446655440000'], ['id' => 'uuid']);
        $v2 = Validator::make(['id' => 'not-a-uuid'], ['id' => 'uuid']);
        $this->assertFalse($v1->fails());
        $this->assertTrue($v2->fails());
    }

    public function test_nullable_field_allows_null(): void
    {
        $v = Validator::make(['notes' => null], ['notes' => 'nullable|string']);
        $this->assertFalse($v->fails());
    }

    public function test_in_validation(): void
    {
        $v1 = Validator::make(['status' => 'active'], ['status' => 'in:active,inactive,pending']);
        $v2 = Validator::make(['status' => 'deleted'], ['status' => 'in:active,inactive,pending']);
        $this->assertFalse($v1->fails());
        $this->assertTrue($v2->fails());
    }

    public function test_multiple_rules_all_validated(): void
    {
        $v = Validator::make(
            ['email' => '', 'name' => ''],
            ['email' => 'required|email', 'name' => 'required|string|min:2']
        );
        $this->assertTrue($v->fails());
        $errors = $v->errors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('name', $errors);
    }
}
