<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Sistem & Akses';
    protected static ?string $navigationLabel = 'Pengguna';
    protected static ?string $modelLabel = 'Pengguna';
    protected static ?string $pluralModelLabel = 'Pengguna';
    protected static ?int $navigationSort = 980;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identitas Pengguna')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Lengkap')->required()->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')->email()->required()->maxLength(255)
                        ->unique(table: User::class, ignoreRecord: true),
                    Forms\Components\TextInput::make('username')
                        ->label('Username')->alphaDash()->maxLength(255)
                        ->unique(table: User::class, ignoreRecord: true)
                        ->helperText('Opsional. Username dapat digunakan untuk login.'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Akun Aktif')->default(true)
                        ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                        ->helperText('Akun nonaktif tidak bisa masuk ke panel. Status akun sendiri terkunci.'),
                ])->columns(2),
            Forms\Components\Section::make('Peran dan Kata Sandi')
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->label('Peran (Shield)')
                        ->multiple()
                        ->relationship(
                            name: 'roles',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query) => $query->where('guard_name', 'web'),
                        )
                        ->searchable()->preload()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                        ->helperText('Peran menentukan kewenangan pengguna. Peran akun sendiri tidak dapat diubah dari sini.')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('password')
                        ->label('Kata Sandi Baru')
                        ->password()->revealable()->confirmed()->minLength(12)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText('Minimal 12 karakter; kosongkan saat mengedit untuk tidak mengubah kata sandi.')
                        ->autocomplete('new-password'),
                    Forms\Components\TextInput::make('password_confirmation')
                        ->label('Konfirmasi Kata Sandi')
                        ->password()->revealable()
                        ->required(fn (Forms\Get $get, string $operation): bool => $operation === 'create' || filled($get('password')))
                        ->dehydrated(false)->autocomplete('new-password'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('username')->label('Username')->searchable()->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('roles.name')->label('Peran')->badge(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->dateTime('d/m/Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Peran')
                    ->relationship('roles', 'name', fn (Builder $query) => $query->where('guard_name', 'web')),
                Tables\Filters\TernaryFilter::make('is_active')->label('Akun Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Kelola'),
            ]);
        // No delete actions: ISO entities and logs reference user identities.
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUsers::route('/')];
    }
}
