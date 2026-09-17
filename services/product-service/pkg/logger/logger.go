package logger

import (
	"go.uber.org/zap"
	"go.uber.org/zap/zapcore"
)

type Logger struct {
	*zap.Logger
}

func New(level string) (*Logger, error) {
	var zapLevel zapcore.Level
	switch level {
	case "debug":
		zapLevel = zapcore.DebugLevel
	case "info":
		zapLevel = zapcore.InfoLevel
	case "warn":
		zapLevel = zapcore.WarnLevel
	case "error":
		zapLevel = zapcore.ErrorLevel
	default:
		zapLevel = zapcore.InfoLevel
	}

	config := zap.NewProductionConfig()
	config.Level = zap.NewAtomicLevelAt(zapLevel)
	config.EncoderConfig.TimeKey = "timestamp"
	config.EncoderConfig.EncodeTime = zapcore.RFC3339NanoTimeEncoder

	logger, err := config.Build()
	if err != nil {
		return nil, err
	}

	return &Logger{logger}, nil
}

func (l *Logger) Close() error {
	return l.Logger.Sync()
}

func (l *Logger) WithField(key string, value interface{}) *Logger {
	return &Logger{l.Logger.With(zap.Any(key, value))}
}

func (l *Logger) WithError(err error) *Logger {
	return &Logger{l.Logger.With(zap.Error(err))}
}

func (l *Logger) WithCompanyID(companyID int64) *Logger {
	return &Logger{l.Logger.With(zap.Int64("company_id", companyID))}
}
