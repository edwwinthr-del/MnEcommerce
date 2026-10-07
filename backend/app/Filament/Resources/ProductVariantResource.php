<?php

namespace App\Filament\Resources;

use App\Models\ProductVariant;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductVariantResource extends Resource
{
    protected static ?string $model = ProductVariant::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Select::make('product_id')->relationship('product', 'name')->required()->searchable(), TextInput::make('name')->required(), TextInput::make('sku')->required()->unique(ignoreRecord: true), TextInput::make('purchase_price')->integer()->minValue(0)->required(), TextInput::make('selling_price')->integer()->minValue(1)->required(), TextInput::make('stock')->integer()->minValue(0)->required(), TextInput::make('supplier_variant_id')])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('product.name')->searchable(), TextColumn::make('name'), TextColumn::make('sku')->searchable(), TextColumn::make('selling_price')->money('EUR', divideBy: 100), TextColumn::make('stock')])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ProductVariantResource\Pages\ManageProductVariants::route('/')];
    }
}
