<?php

namespace App\Filament\Resources;

use App\Models\SupplierOrder;
use App\Services\SupplierOrderService;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupplierOrderResource extends Resource
{
    protected static ?string $model = SupplierOrder::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Suppliers';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('order_id')->relationship('order', 'order_number')->required()->searchable(), Select::make('supplier_id')->relationship('supplier', 'name')->required()->searchable()->preload(),
            TextInput::make('supplier_order_number')->maxLength(180), TextInput::make('cost')->label('Actual supplier cost (EUR cents)')->integer()->minValue(0)->required(), TextInput::make('shipping_cost')->integer()->minValue(0)->default(0),
            TextInput::make('tracking_number')->maxLength(180), TextInput::make('tracking_url')->url()->maxLength(2048), Select::make('status')->options(['pending' => 'Pending', 'ordered' => 'Ordered', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'])->default('pending')->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order.order_number')->searchable(), TextColumn::make('supplier.name'), TextColumn::make('supplier_order_number')->searchable(), TextColumn::make('cost')->money('EUR', divideBy: 100), TextColumn::make('status')->badge(), TextColumn::make('tracking_number'),
        ])->recordActions([EditAction::make()->using(fn (SupplierOrder $record, array $data) => app(SupplierOrderService::class)->save($record, $data, auth()->id()))])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => SupplierOrderResource\Pages\ManageSupplierOrders::route('/')];
    }
}
