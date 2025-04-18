<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Office;
use App\Models\Section;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    public static function canViewAny(): bool
    {
        return \Illuminate\Support\Facades\Auth::user()?->role === UserRole::ROOT;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(3)
            ->disabled(fn (User $record): bool => $record->trashed())
            ->schema([
                Forms\Components\FileUpload::make('avatar')
                    ->alignCenter()
                    ->avatar(),
                Forms\Components\Group::make()
                    ->columnSpan(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('role')
                            ->options(UserRole::class)
                            ->required()
                            ->default(UserRole::USER->value),
                        Forms\Components\Select::make('office_id')
                            ->label('Office')
                            ->searchable()
                            ->relationship('office', 'name')
                            ->getOptionLabelUsing(fn ($value): ?string => Office::find($value)?->name)
                            ->placeholder('Select Office')
                            ->preload()
                            ->required()
                            ->reactive()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('acronym')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('head_name'),
                                Forms\Components\TextInput::make('designation'),
                            ])
                            ->createOptionUsing(fn (array $data): Office => Office::create($data)),

                        Forms\Components\Select::make('section_id')
                            ->label('Section')
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->relationship('section', 'name')
                            ->getOptionLabelUsing(fn ($value): ?string => Section::find($value)?->name)
                            ->placeholder('Select Section')
                            ->preload()
                            ->options(function (callable $get) {

                                $officeId = $get('office_id');

                                if ($officeId) {
                                    return Section::where('office_id', $officeId)->pluck('name', 'id');
                                }

                                return Section::pluck('name', 'id');
                            })
                            ->createOptionForm([
                                Forms\Components\Select::make('office_id')
                                    ->label('Office')
                                    ->relationship('office', 'name')
                                    ->required(),
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('head_name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('designation')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(fn (array $data): Section => Section::create($data)),
                    ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('office.name')
                    ->label('Office')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('section.name')
                    ->label('Section')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deactivated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Deactivated At'),
                Tables\Columns\TextColumn::make('deactivated_by')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Deactivated By')
                    ->getStateUsing(function (User $record) {
                        return $record->deactivated_by ? User::find($record->deactivated_by)->name : null;
                    }),
                Tables\Columns\TextColumn::make('trashed')
                    ->label('Deleted At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->getStateUsing(function (User $record) {
                        return $record->deleted_at ? $record->deleted_at : null;
                    }),
                Tables\Columns\TextColumn::make('role'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make('trashed'),
                Tables\Filters\Filter::make('deactivated')
                    ->query(fn (Builder $query): Builder => $query->whereNull('deactivated_at'))
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                    Tables\Actions\ForceDeleteAction::make(),
                    Tables\Actions\Action::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->visible(fn (User $record): bool => is_null($record->deactivated_at))
                        ->action(fn (User $record, User $user) => $record->deactivate($user)),
                ]),
                Tables\Actions\Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => ! is_null($record->deactivated_at))
                    ->action(fn (User $record) => $record->reactivate()),
            ])
            ->bulkActions([
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])->withTrashed()
            ->where('id', '!=', Auth::id());

    }
}
