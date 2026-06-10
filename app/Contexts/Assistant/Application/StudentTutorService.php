<?php

declare(strict_types=1);

namespace App\Contexts\Assistant\Application;

use App\Contexts\Assistant\Domain\AssistantMode;
use App\Contexts\Assistant\Domain\AssistantReply;
use App\Contexts\Assistant\Domain\ChatMessage;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Platform\Application\FeatureFlags;
use Illuminate\Support\Str;

/**
 * The learner tutor «مُعين». Grounds every answer in the course's own
 * content (retrieval), refuses to solve graded assessments, and — depending
 * on the admin-selected mode — answers either deterministically (rules) or
 * via Claude. Disabled entirely in `off` mode.
 */
final class StudentTutorService
{
    public function __construct(
        private readonly FeatureFlags $flags,
        private readonly CourseKnowledgeRetriever $retriever,
        private readonly AssistantEngine $engine,
    ) {}

    public function mode(): AssistantMode
    {
        return AssistantMode::tryFrom($this->flags->assistantMode()) ?? AssistantMode::Off;
    }

    /**
     * @param  list<ChatMessage>  $history
     */
    public function answer(Course $course, string $question, array $history = []): AssistantReply
    {
        // Guardrail: never hand over answers to graded work.
        if ($this->looksLikeCheating($question)) {
            return new AssistantReply(
                'لا أستطيع تقديم حلول الاختبارات أو الواجبات المُقيَّمة — لكن يسعدني أن أشرح '
                .'الفكرة أو أوجّهك للدرس المناسب لتصل للإجابة بنفسك.',
            );
        }

        $passages = $this->retriever->retrieve($course, $question);
        $sources = array_values(array_unique(array_map(
            static fn (array $p): string => $p['title'],
            $passages,
        )));

        if ($this->mode() === AssistantMode::Rules) {
            return $this->rulesAnswer($passages, $sources);
        }

        // Claude mode (real engine when a key is set, Fake otherwise).
        $context = $this->contextBlock($course, $passages);
        $system = <<<SYS
            أنت «مُعين»، مساعد تعليمي ودود في منصة دورات عربية. مهمتك مساعدة الطالب على فهم
            محتوى دورة «{$course->title}» وإكمال تعلّمه. التزم بالآتي:
            - أجب بالعربية وبإيجاز ووضوح، مستنداً إلى السياق المرفق فقط.
            - إن لم يكفِ السياق، قل ذلك بصراحة وأرشد الطالب للدرس المناسب.
            - لا تقدّم حلول الاختبارات أو الواجبات المُقيَّمة؛ اشرح الفكرة بدلاً من ذلك.
            <context>
            {$context}
            </context>
            SYS;

        $messages = [...$history, ChatMessage::user($question)];
        $text = $this->engine->reply($system, $messages, (string) config('ai.models.tutor'));

        return new AssistantReply($text, $sources);
    }

    /**
     * @param  list<array{title: string, snippet: string}>  $passages
     * @param  list<string>  $sources
     */
    private function rulesAnswer(array $passages, array $sources): AssistantReply
    {
        $body = collect($passages)
            ->map(fn (array $p): string => "• من درس «{$p['title']}»:\n{$p['snippet']}")
            ->implode("\n\n");

        $text = "إليك ما وجدته في محتوى الدورة بخصوص سؤالك:\n\n{$body}\n\n"
            .'راجع الدروس المذكورة لمزيد من التفصيل، وإن احتجت شرحاً أعمق فاطرح سؤالاً أدق.';

        return new AssistantReply($text, $sources, ['افتح الدرس المرتبط', 'اطرح سؤالاً أكثر تحديداً']);
    }

    /**
     * @param  list<array{title: string, snippet: string}>  $passages
     */
    private function contextBlock(Course $course, array $passages): string
    {
        $lines = ["الدورة: {$course->title}"];
        foreach ($passages as $p) {
            $lines[] = "درس «{$p['title']}»: {$p['snippet']}";
        }

        return implode("\n", $lines);
    }

    private function looksLikeCheating(string $question): bool
    {
        $q = Str::lower($question);

        foreach (['حل الاختبار', 'حل الواجب', 'إجابات الاختبار', 'اجابات الاختبار', 'حل الكويز', 'answer the quiz', 'exam answers'] as $needle) {
            if (Str::contains($q, Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }
}
