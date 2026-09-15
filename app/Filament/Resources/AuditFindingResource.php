<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditFindingResource\Pages;
use App\Models\AuditFinding;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditFindingResource extends Resource
{
    protected static ?string $model = AuditFinding::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'Audit & Improvement';
    protected static ?string $modelLabel = 'Finding';
    protected static ?string $pluralModelLabel = 'Finding / NCR / OFI';
    protected static ?int $navigationSort = 120;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('audit_id')->label('Audit')->relationship('audit', 'title')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('code')->label('Kode Finding')->required()->unique(ignoreRecord: true),
            Forms\Components\Select::make('finding_type')->label('Jenis Finding')->options(['nonconformity' => 'Nonconformity','observation' => 'Observation','opportunity' => 'Opportunity for Improvement','conforming' => 'Conforming'])->required()->default('nonconformity'),
            Forms\Components\Select::make('severity')->label('Severity')->options(['major' => 'Major','minor' => 'Minor']),
            Forms\Components\TextInput::make('title')->label('Judul')->required()->columnSpanFull(),
            Forms\Components\Select::make('requirement_id')->label('Requirement')->relationship('requirement', 'title')->searchable()->preload(),
            Forms\Components\Select::make('control_id')->label('Control')->relationship('control', 'title')->searchable()->preload(),
            Forms\Components\Select::make('owner_id')->label('PIC')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\DatePicker::make('due_date')->label('Due Date')->native(false),
            Forms\Components\Select::make('status')->label('Status')->options(['open' => 'Open','analysis' => 'Root Cause Analysis','action' => 'Corrective Action','verification' => 'Verification','closed' => 'Closed'])->required()->default('open'),
            Forms\Components\Textarea::make('description')->label('Pernyataan Finding / Bukti Objektif')->required()->columnSpanFull(),
            Forms\Components\Textarea::make('root_cause')->label('Root Cause')->columnSpanFull(),
            Forms\Components\Textarea::make('correction')->label('Correction / Immediate Fix')->columnSpanFull(),
            Forms\Components\Repeater::make('correctiveActions')->label('Corrective Actions')->relationship()->schema([
                Forms\Components\Textarea::make('action')->label('Action')->required()->columnSpanFull(),
                Forms\Components\Select::make('owner_id')->label('Owner')->relationship('owner', 'name')->searchable()->preload(),
                Forms\Components\DatePicker::make('due_date')->label('Due Date')->native(false),
                Forms\Components\Select::make('status')->label('Status')->options(['open' => 'Open','in_progress' => 'In Progress','verification' => 'Verification','completed' => 'Completed'])->required()->default('open'),
                Forms\Components\DateTimePicker::make('completed_at')->label('Completed At')->seconds(false),
                Forms\Components\Select::make('verified_by')->label('Verified By')->relationship('verifier', 'name')->searchable()->preload(),
                Forms\Components\DateTimePicker::make('verified_at')->label('Verified At')->seconds(false),
                Forms\Components\Textarea::make('verification_notes')->label('Verification Notes')->columnSpanFull(),
                Forms\Components\Select::make('effectiveness_status')->label('Efektivitas')->options(['pending' => 'Pending','effective' => 'Effective','ineffective' => 'Ineffective'])->required()->default('pending'),
                Forms\Components\Textarea::make('effectiveness_notes')->label('Effectiveness Notes')->columnSpanFull(),
            ])->columns(2)->columnSpanFull()->collapsible(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('audit.code')->label('Audit')->sortable(),
            Tables\Columns\TextColumn::make('title')->label('Finding')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('finding_type')->label('Jenis')->badge(),
            Tables\Columns\TextColumn::make('severity')->label('Severity')->badge()->placeholder('-'),
            Tables\Columns\TextColumn::make('owner.name')->label('PIC')->placeholder('-'),
            Tables\Columns\TextColumn::make('due_date')->label('Due')->date('d/m/Y')->placeholder('-'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('corrective_actions_count')->label('CAPA')->counts('correctiveActions')->alignCenter(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageAuditFindings::route('/')]; }
}
