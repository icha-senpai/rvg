<?php

namespace App\Application\Operations\Presenters;

use App\Models\Operation;

class OperationPresenterRelations
{
    public static function loadForSummary(Operation $operation): Operation
    {
        return $operation->loadMissing([
            'squadron',
            'squadron.emblem',
            'squadron.leader',
            'creator',
            'creator.roles',
        ]);
    }

    public static function loadForFull(Operation $operation): Operation
    {
        return $operation->loadMissing([
            'squadron',
            'squadron.emblem',
            'creator',
            'creator.roles',
            'participants.user',
            'roles.participants.user',
            'images',
        ]);
    }

    public static function loadForForm(Operation $operation): Operation
    {
        return $operation->loadMissing([
            'images',
            'roles',
        ]);
    }
}
