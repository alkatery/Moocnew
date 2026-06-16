<?php

declare(strict_types=1);

use App\Contexts\Assessment\Domain\AnswerGrader;
use App\Contexts\Assessment\Domain\QuestionType;

/**
 * اختبارات وحدة لـ AnswerGrader — الأنواع الجديدة (E4).
 * بلا قاعدة بيانات — Domain نقي.
 */
beforeEach(function () {
    $this->grader = new AnswerGrader;
});

// ================================================================ dropdown

it('grades dropdown correctly — matching single choice', function () {
    expect($this->grader->isCorrect(QuestionType::Dropdown, ['b'], ['b']))->toBeTrue();
});

it('grades dropdown incorrectly — wrong choice', function () {
    expect($this->grader->isCorrect(QuestionType::Dropdown, ['b'], ['a']))->toBeFalse();
});

it('grades dropdown incorrectly — empty answer', function () {
    expect($this->grader->isCorrect(QuestionType::Dropdown, ['b'], []))->toBeFalse();
});

it('grades dropdown incorrectly — null answer', function () {
    expect($this->grader->isCorrect(QuestionType::Dropdown, ['b'], null))->toBeFalse();
});

it('grades dropdown incorrectly — string answer instead of array', function () {
    expect($this->grader->isCorrect(QuestionType::Dropdown, ['b'], 'b'))->toBeFalse();
});

// ================================================================ multi_select

it('grades multi_select correctly — order-independent match', function () {
    expect($this->grader->isCorrect(QuestionType::MultiSelect, ['a', 'c'], ['c', 'a']))->toBeTrue();
});

it('grades multi_select incorrectly — partial selection (لا جزئي)', function () {
    expect($this->grader->isCorrect(QuestionType::MultiSelect, ['a', 'c'], ['a']))->toBeFalse();
});

it('grades multi_select incorrectly — extra wrong choice', function () {
    expect($this->grader->isCorrect(QuestionType::MultiSelect, ['a', 'c'], ['a', 'c', 'b']))->toBeFalse();
});

it('grades multi_select incorrectly — empty answer', function () {
    expect($this->grader->isCorrect(QuestionType::MultiSelect, ['a', 'c'], []))->toBeFalse();
});

it('grades multi_select incorrectly — null answer', function () {
    expect($this->grader->isCorrect(QuestionType::MultiSelect, ['a', 'c'], null))->toBeFalse();
});

// ================================================================ numerical

it('grades numerical correctly — within tolerance', function () {
    expect($this->grader->isCorrect(QuestionType::Numerical, [100], '101', ['tolerance' => 2]))->toBeTrue()
        ->and($this->grader->isCorrect(QuestionType::Numerical, [100], '98', ['tolerance' => 2]))->toBeTrue();
});

it('grades numerical correctly — on the tolerance boundary (شامل)', function () {
    expect($this->grader->isCorrect(QuestionType::Numerical, [100], '102', ['tolerance' => 2]))->toBeTrue()
        ->and($this->grader->isCorrect(QuestionType::Numerical, [100], '98', ['tolerance' => 2]))->toBeTrue();
});

it('grades numerical incorrectly — just outside tolerance', function () {
    expect($this->grader->isCorrect(QuestionType::Numerical, [100], '103', ['tolerance' => 2]))->toBeFalse()
        ->and($this->grader->isCorrect(QuestionType::Numerical, [100], '97', ['tolerance' => 2]))->toBeFalse();
});

it('grades numerical correctly — exact match with zero tolerance', function () {
    expect($this->grader->isCorrect(QuestionType::Numerical, [3.14], '3.14', ['tolerance' => 0]))->toBeTrue();
});

it('grades numerical incorrectly — mismatch with zero tolerance', function () {
    expect($this->grader->isCorrect(QuestionType::Numerical, [3.14], '3.15', ['tolerance' => 0]))->toBeFalse();
});

it('grades numerical incorrectly — non-numeric answer', function () {
    expect($this->grader->isCorrect(QuestionType::Numerical, [100], 'كثير', ['tolerance' => 0]))->toBeFalse();
});

it('grades numerical incorrectly — null answer', function () {
    expect($this->grader->isCorrect(QuestionType::Numerical, [100], null, ['tolerance' => 0]))->toBeFalse();
});

it('grades numerical — integer answer accepted as numeric', function () {
    expect($this->grader->isCorrect(QuestionType::Numerical, [42], 42, ['tolerance' => 0]))->toBeTrue();
});

// ================================================================ regex

it('grades regex correctly — matching pattern', function () {
    expect($this->grader->isCorrect(QuestionType::Regex, ['^\\d{3}$'], '123', []))->toBeTrue();
});

it('grades regex incorrectly — pattern does not match', function () {
    expect($this->grader->isCorrect(QuestionType::Regex, ['^\\d{3}$'], '12', []))->toBeFalse()
        ->and($this->grader->isCorrect(QuestionType::Regex, ['^\\d{3}$'], 'abc', []))->toBeFalse();
});

it('grades regex correctly — case-insensitive flag i', function () {
    expect($this->grader->isCorrect(QuestionType::Regex, ['^yes$'], 'YES', ['flags' => 'i']))->toBeTrue();
});

it('grades regex incorrectly — case-sensitive without flag i', function () {
    expect($this->grader->isCorrect(QuestionType::Regex, ['^yes$'], 'YES', []))->toBeFalse();
});

it('grades regex safely — answer exceeding length limit returns false without exception', function () {
    $longAnswer = str_repeat('أ', 2001);
    // يجب أن يُعاد false بلا استثناء، بلا توقّف
    expect($this->grader->isCorrect(QuestionType::Regex, ['^.*$'], $longAnswer, []))->toBeFalse();
});

it('grades regex safely — failed preg_match returns false not exception', function () {
    // نمط فاشل لو وصل لـ preg_match — يُعاد false بأمان
    // نمرّره مع كاتم الأخطاء داخلياً
    $result = $this->grader->isCorrect(QuestionType::Regex, ['valid_pattern'], 'test', []);
    expect($result)->toBeBool();
});

it('grades regex — non-string answer returns false', function () {
    expect($this->grader->isCorrect(QuestionType::Regex, ['^\\d+$'], null, []))->toBeFalse()
        ->and($this->grader->isCorrect(QuestionType::Regex, ['^\\d+$'], 123, []))->toBeFalse();
});

it('grades regex — Arabic text with unicode flag u', function () {
    expect($this->grader->isCorrect(QuestionType::Regex, ['^[\\p{Arabic}]+$'], 'مرحبا', []))->toBeTrue()
        ->and($this->grader->isCorrect(QuestionType::Regex, ['^[\\p{Arabic}]+$'], 'hello', []))->toBeFalse();
});

// ================================================================ عدم كسر الأنواع القائمة

it('existing MCQ grading still works after E4 (لا كسر)', function () {
    expect($this->grader->isCorrect(QuestionType::Mcq, ['b'], ['b']))->toBeTrue()
        ->and($this->grader->isCorrect(QuestionType::Mcq, ['a', 'c'], ['c', 'a']))->toBeTrue()
        ->and($this->grader->isCorrect(QuestionType::Mcq, ['b'], ['a']))->toBeFalse();
});

it('existing TrueFalse grading still works after E4 (لا كسر)', function () {
    expect($this->grader->isCorrect(QuestionType::TrueFalse, true, true))->toBeTrue()
        ->and($this->grader->isCorrect(QuestionType::TrueFalse, true, false))->toBeFalse()
        ->and($this->grader->isCorrect(QuestionType::TrueFalse, true, 'true'))->toBeFalse();
});

it('existing ShortAnswer grading still works after E4 (لا كسر)', function () {
    expect($this->grader->isCorrect(QuestionType::ShortAnswer, ['الصلاة'], '  الصَّلاة '))->toBeTrue()
        ->and($this->grader->isCorrect(QuestionType::ShortAnswer, ['Laravel'], 'laravel'))->toBeTrue()
        ->and($this->grader->isCorrect(QuestionType::ShortAnswer, ['الصلاة'], 'زكاة'))->toBeFalse();
});
