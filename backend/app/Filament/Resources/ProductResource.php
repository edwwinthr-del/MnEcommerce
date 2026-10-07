<?php

namespace App\Filament\Resources;

use App\Models\Product;
use App\Services\MarginProtectionService;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->maxLength(180), TextInput::make('slug')->required()->regex('/^[a-z0-9-]+$/')->unique(ignoreRecord: true), TextInput::make('sku')->required()->unique(ignoreRecord: true), TextInput::make('brand')->maxLength(120), Select::make('category_id')->relationship('category', 'name')->required()->searchable()->preload(), Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload(), Textarea::make('short_description')->maxLength(500), Textarea::make('description')->maxLength(20000), KeyValue::make('specifications'), TextInput::make('purchase_price')->label('Purchase price (EUR cents)')->required()->integer()->minValue(0)->maxValue(100000000), TextInput::make('selling_price')->label('Selling price (EUR cents)')->required()->integer()->minValue(1)->maxValue(100000000), TextInput::make('old_price')->integer()->minValue(0), TextInput::make('shipping_cost')->label('Supplier shipping (EUR cents / unit)')->integer()->minValue(0)->default(0), TextInput::make('minimum_margin')->label('Minimum gross profit (EUR cents)')->integer()->minValue(0)->default(0), Toggle::make('disable_on_low_margin')->default(true), Toggle::make('track_stock')->default(true), TextInput::make('stock')->integer()->minValue(0)->default(0), TextInput::make('estimated_delivery_min')->integer()->minValue(1)->maxValue(90)->default(3), TextInput::make('estimated_delivery_max')->integer()->minValue(1)->maxValue(90)->gte('estimated_delivery_min')->default(7), TextInput::make('supplier_url')->url()->maxLength(2048), TextInput::make('supplier_product_id')->maxLength(255), Select::make('status')->options(['draft' => 'Draft', 'active' => 'Active', 'disabled' => 'Disabled'])->required()->default('draft'), Toggle::make('is_featured'), Toggle::make('is_new'), TextInput::make('seo_title')->maxLength(70), Textarea::make('seo_description')->maxLength(160),
            Repeater::make('images')->relationship()->schema([FileUpload::make('image_url')->label('Product image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5120)->disk('public')->directory('products')->visibility('public')->required(), Toggle::make('is_primary')])->orderColumn('sort_order')->reorderable()->collapsible()->columnSpanFull(),
            Repeater::make('variants')->relationship()->schema([TextInput::make('name')->required(), TextInput::make('sku')->required()->unique(ignoreRecord: true), TextInput::make('purchase_price')->integer()->minValue(0)->required(), TextInput::make('selling_price')->integer()->minValue(1)->required(), TextInput::make('stock')->integer()->minValue(0)->required(), TextInput::make('supplier_variant_id')])->collapsible()->columnSpanFull()])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable()->sortable(), TextColumn::make('category.name'), TextColumn::make('selling_price')->money('EUR', divideBy: 100)->sortable(), TextColumn::make('gross_profit')->money('EUR', divideBy: 100), TextColumn::make('gross_margin'), TextColumn::make('stock'), TextColumn::make('status')->badge(), IconColumn::make('margin_flagged')->boolean()])->recordActions([EditAction::make()->using(fn (Product $record, array $data) => app(MarginProtectionService::class)->save($record, $data, auth()->id()))])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ProductResource\Pages\ManageProducts::route('/')];
    }
}
