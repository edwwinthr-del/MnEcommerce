<?php

namespace App\Filament\Resources;

use App\Models\Banner;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('title')->required()->maxLength(180), Textarea::make('subtitle')->maxLength(500), FileUpload::make('image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5120)->disk('public')->directory('banners')->visibility('public')->required(), FileUpload::make('mobile_image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5120)->disk('public')->directory('banners')->visibility('public'), TextInput::make('button_text')->maxLength(50), TextInput::make('button_url')->rules(['nullable', 'regex:~^/(?!/)[a-zA-Z0-9/_?=&%.-]*$~'])->helperText('Relative storefront path, for example /products'), Select::make('position')->options(['hero' => 'Homepage hero'])->default('hero')->required(), TextInput::make('sort_order')->integer()->default(0), DateTimePicker::make('starts_at'), DateTimePicker::make('ends_at')->after('starts_at'), Toggle::make('is_active')->default(true)])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->searchable(), TextColumn::make('position'), TextColumn::make('starts_at')->dateTime(), TextColumn::make('ends_at')->dateTime(), IconColumn::make('is_active')->boolean()])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => BannerResource\Pages\ManageBanners::route('/')];
    }
}
