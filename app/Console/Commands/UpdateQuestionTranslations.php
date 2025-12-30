<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateQuestionTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-question-translations';
    // php artisan app:update-question-translations
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cross-reference Italian translations with questions and store matched IDs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $translations = \App\Models\Translation::whereNotNull('text_it')
            ->where('text_it', '!=', '')
            ->get(['id', 'text_it']);

        $questions = \App\Models\Question::all();

        $this->info("Processing " . $questions->count() . " questions against " . $translations->count() . " translations...");

        $bar = $this->output->createProgressBar($questions->count());
        $bar->start();

        foreach ($questions as $question) {
            $matchedIds = [];
            $questionText = $question->text;

            foreach ($translations as $translation) {
                // Use mb_stripos for case-insensitive phrase matching
                if (mb_stripos($questionText, $translation->text_it) !== false) {
                    $matchedIds[] = $translation->id;
                }
            }

            // Ensure unique IDs (though translations should be unique anyway)
            $question->translation_ids = array_values(array_unique($matchedIds));
            $question->save();
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nUpdate completed successfully!");
    }
}
