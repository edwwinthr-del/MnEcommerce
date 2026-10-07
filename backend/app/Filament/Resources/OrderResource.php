<?php

namespace App\Filament\Resources;

use App\Models\Order;
use App\Services\OrderWorkflow;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('order_number')->disabled(), TextInput::make('customer_name')->disabled(), TextInput::make('customer_email')->disabled(), TextInput::make('customer_phone')->disabled(),
            Textarea::make('shipping_address')->disabled(), TextInput::make('shipping_city')->disabled(), TextInput::make('shipping_postal_code')->disabled(), TextInput::make('payment_method')->disabled(),
            TextInput::make('total')->label('Total EUR cents')->disabled(), Textarea::make('customer_note')->disabled(), Textarea::make('admin_note')->maxLength(3000),
            Repeater::make('items')->relationship()->disabled()->schema([TextInput::make('product_name'), TextInput::make('sku'), TextInput::make('quantity'), TextInput::make('purchase_price'), TextInput::make('selling_price'), TextInput::make('supplier_url')])->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order_number')->searchable(), TextColumn::make('customer_name')->searchable(), TextColumn::make('total')->money('EUR', divideBy: 100), TextColumn::make('order_status')->badge(), TextColumn::make('payment_status')->badge(), TextColumn::make('created_at')->dateTime()->sortable(),
        ])->recordActions([
            EditAction::make()->label('Details'),
            Action::make('changeStatus')->label('Change status')->authorize('update')->schema([Select::make('order_status')->options(fn (Order $record) => array_combine(OrderWorkflow::TRANSITIONS[$record->order_status], OrderWorkflow::TRANSITIONS[$record->order_status]))->required()])->action(fn (Order $record, array $data) => app(OrderWorkflow::class)->transition($record, $data['order_status'], auth()->id())),
            Action::make('recordPayment')->label('Record verified payment')->authorize('update')->requiresConfirmation()->modalDescription('Confirm only after independently verifying receipt of funds.')->visible(fn (Order $record) => $record->payment_status === 'unpaid' && ! in_array($record->order_status, ['cancelled', 'refunded']))->action(fn (Order $record) => app(OrderWorkflow::class)->recordPayment($record, auth()->id())),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => OrderResource\Pages\ManageOrders::route('/')];
    }
}
