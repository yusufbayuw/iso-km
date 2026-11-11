<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManagementReviewResource\Pages;
use App\Models\ManagementReview;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ManagementReviewResource extends Resource
{
    use \App\Filament\Resources\Concerns\ScopesByManagementSystem;

    protected static ?string $model = ManagementReview::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Performance & Review';
    protected static ?string $modelLabel = 'Management Review';
    protected static ?string $pluralModelLabel = 'Management Review';
    protected static ?int $navigationSort = 160;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('code')->label('Kode Review')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('title')->label('Judul')->required()->columnSpanFull(),
            Forms\Components\Select::make('status')->label('Status')->options(['planned' => 'Planned','scheduled' => 'Scheduled','held' => 'Held','completed' => 'Completed','cancelled' => 'Cancelled'])->required()->default('planned'),
            Forms\Components\Select::make('chairperson_id')->label('Chairperson')->relationship('chairperson', 'name')->searchable()->preload(),
            Forms\Components\DateTimePicker::make('scheduled_at')->label('Jadwal')->seconds(false),
            Forms\Components\DateTimePicker::make('held_at')->label('Pelaksanaan')->seconds(false),
            Forms\Components\Select::make('attendees')->label('Peserta')->relationship('attendees', 'name')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('inputs_summary')->label('Ringkasan Input Management Review')->columnSpanFull(),
            Forms\Components\Textarea::make('outputs_summary')->label('Ringkasan Output')->columnSpanFull(),
            Forms\Components\Textarea::make('decisions')->label('Keputusan Manajemen')->columnSpanFull(),
            Forms\Components\Repeater::make('actions')->label('Action Items')->relationship()->schema([
                Forms\Components\Textarea::make('action')->label('Action')->required()->columnSpanFull(),
                Forms\Components\Select::make('owner_id')->label('Owner')->relationship('owner', 'name')->searchable()->preload(),
                Forms\Components\DatePicker::make('due_date')->label('Due Date')->native(false),
                Forms\Components\Select::make('status')->label('Status')->options(['open' => 'Open','in_progress' => 'In Progress','completed' => 'Completed'])->required()->default('open'),
                Forms\Components\DateTimePicker::make('completed_at')->label('Completed At')->seconds(false),
                Forms\Components\Textarea::make('notes')->label('Catatan')->columnSpanFull(),
            ])->columns(2)->columnSpanFull()->collapsible(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('Review')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->sortable(),
            Tables\Columns\TextColumn::make('chairperson.name')->label('Chairperson')->placeholder('-'),
            Tables\Columns\TextColumn::make('scheduled_at')->label('Jadwal')->dateTime('d/m/Y H:i')->placeholder('-'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('actions_count')->label('Action')->counts('actions')->alignCenter(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageManagementReviews::route('/')]; }
}
