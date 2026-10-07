<?php

namespace App\Filament\Resources;

use App\Models\Category;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->maxLength(120), TextInput::make('slug')->required()->maxLength(120)->regex('/^[a-z0-9-]+$/')->unique(ignoreRecord: true), Textarea::make('description')->maxLength(3000), TextInput::make('image')->url()->maxLength(2048), Toggle::make('is_active')->default(true), TextInput::make('sort_order')->integer()->default(0)])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable()->sortable(), TextColumn::make('slug'), IconColumn::make('is_active')->boolean()])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => CategoryResource\Pages\ManageCategorys::route('/')];
    }
}
