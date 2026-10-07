<?php

namespace App\Filament\Resources;

use App\Models\Supplier;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Suppliers';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->maxLength(180), TextInput::make('website')->url(), TextInput::make('contact_name')->maxLength(180), TextInput::make('email')->email(), TextInput::make('api_url')->url()->helperText('Stored metadata only; no automatic requests are made.'), TextInput::make('api_key')->password()->revealable(false)->dehydrated(fn ($state) => filled($state))->afterStateHydrated(fn ($component) => $component->state(null))->helperText('Leave blank to retain the encrypted credential.'), Toggle::make('is_active')->default(true)])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('website'), TextColumn::make('contact_name'), IconColumn::make('is_active')->boolean()])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => SupplierResource\Pages\ManageSuppliers::route('/')];
    }
}
