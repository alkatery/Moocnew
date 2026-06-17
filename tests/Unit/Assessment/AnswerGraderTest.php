<?php

declare(strict_types=1);

use App\Contexts\Assessment\Domain\AnswerGrader;
use App\Contexts\Assessment\Domain\QuestionType;

beforeEach(function () {
    $this->grader = new AnswerGrader;
});

it('grades a single-answer MCQ', function () {
    expect($this->grader->isCorrect(QuestionType::Mcq, ['b'], ['b']))->toBeTrue();
    expect($this->grader->isCorrect(QuestionType::Mcq, ['b'], ['a']))->toBeFalse();
});

it('grades a multi-answer MCQ regardless of order', function () {
    expect($this->grader->isCorrect(QuestionType::Mcq, ['a', 'c'], ['c', 'a']))->toBeTrue();
    expect($this->grader->isCorrect(QuestionType::Mcq, ['a', 'c'], ['a']))->toBeFalse();
});

it('grades true/false', function () {
    expect($this->grader->isCorrect(QuestionType::TrueFalse, true, true))->toBeTrue();
    expect($this->grader->isCorrect(QuestionType::TrueFalse, true, false))->toBeFalse();
    // a non-boolean answer is never correct
    expect($this->grader->isCorrect(QuestionType::TrueFalse, true, 'true'))->toBeFalse();
});

it('grades a short answer ignoring case, whitespace and Arabic diacritics', function () {
    $accepted = ['الصلاة', 'صلاة'];

    expect($this->grader->isCorrect(QuestionType::ShortAnswer, $accepted, '  الصَّلاة '))->toBeTrue();
    expect($this->grader->isCorrect(QuestionType::ShortAnswer, $accepted, 'صلاة'))->toBeTrue();
    expect($this->grader->isCorrect(QuestionType::ShortAnswer, $accepted, 'زكاة'))->toBeFalse();
});

it('grades a short answer case-insensitively for latin text', function () {
    expect($this->grader->isCorrect(QuestionType::ShortAnswer, ['Laravel'], 'laravel'))->toBeTrue();
});
