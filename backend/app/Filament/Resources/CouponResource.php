<?php

namespace App\Filament\Resources;

use App\Models\Coupon;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('code')->required()->maxLength(50)->regex('/^[A-Za-z0-9_-]+$/')->unique(ignoreRecord: true), Select::make('type')->options(['percentage' => 'Percentage', 'fixed' => 'Fixed EUR cents'])->required()->live(), TextInput::make('amount')->required()->integer()->minValue(1)->maxValue(fn (Get $get) => $get('type') === 'percentage' ? 100 : 1000000), TextInput::make('minimum_order')->integer()->minValue(0)->default(0), TextInput::make('usage_limit')->integer()->minValue(1), DateTimePicker::make('starts_at'), DateTimePicker::make('ends_at')->after('starts_at'), Toggle::make('active')->default(true)])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('code')->searchable(), TextColumn::make('type'), TextColumn::make('amount'), TextColumn::make('usage_count'), TextColumn::make('usage_limit'), IconColumn::make('active')->boolean()])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => CouponResource\Pages\ManageCoupons::route('/')];
    }
}
