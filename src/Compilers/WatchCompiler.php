<?php


namespace Umbrellio\Postgres\Compilers;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;

class WatchCompiler
{
    public static function compile(Grammar $grammar, Blueprint $blueprint, Fluent $command, array $watches = []): array
    {
        $commands = [];
        if ($watches['onUpdate']) {
            $commands[] = FunctionsCompiler::compile('onUpdateFunction');
            $commands[] = sprintf(
                'create trigger %s_table before update on %s for each row execute function %s()',
                FunctionsCompiler::FUNCTION['on_update'],
                $grammar->wrapTable($blueprint),
                FunctionsCompiler::FUNCTION['on_update']
            );
        }
        if ($watches['onInsert']) {
            $commands[] = FunctionsCompiler::compile('onInsertFunction');
            $commands[] = sprintf(
                'create trigger %s_table before insert on %s for each row execute function %s()',
                FunctionsCompiler::FUNCTION['on_insert'],
                $grammar->wrapTable($blueprint),
                FunctionsCompiler::FUNCTION['on_insert']
            );
        }
        if ($watches['onDelete']) {
            $commands[] = FunctionsCompiler::compile('onDeleteFunction');
            $commands[] = sprintf(
                'create trigger %s_table before delete on %s for each row execute function %s()',
                FunctionsCompiler::FUNCTION['on_delete'],
                $grammar->wrapTable($blueprint),
                FunctionsCompiler::FUNCTION['on_delete']
            );
        }

        return array_filter($commands);
    }
}
