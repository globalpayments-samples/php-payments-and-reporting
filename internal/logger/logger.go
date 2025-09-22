package logger

import (
	"log"
	"time"
)

type Level int
type Channel string

const (
	DEBUG Level = iota
	INFO
	WARNING
	ERROR
	CRITICAL
)

const (
	ChannelSecurity     Channel = "security"
	ChannelAPI         Channel = "api"
	ChannelVerification Channel = "verification"
	ChannelSystem      Channel = "system"
)

type Logger struct {
	logLevel     Level
	enableFile   bool
	enableSyslog bool
	logDir       string
	context      map[string]interface{}
}

func New(logLevel string, logDir string, enableFile bool) *Logger {
	level := INFO
	switch logLevel {
	case "debug":
		level = DEBUG
	case "warning":
		level = WARNING
	case "error":
		level = ERROR
	case "critical":
		level = CRITICAL
	}

	return &Logger{
		logLevel:   level,
		enableFile: enableFile,
		logDir:     logDir,
		context:    make(map[string]interface{}),
	}
}

func (l *Logger) Debug(message string, context map[string]interface{}, channel Channel) {
	if l.logLevel <= DEBUG {
		l.writeLog("DEBUG", message, context, channel)
	}
}

func (l *Logger) Info(message string, context map[string]interface{}, channel Channel) {
	if l.logLevel <= INFO {
		l.writeLog("INFO", message, context, channel)
	}
}

func (l *Logger) Warning(message string, context map[string]interface{}, channel Channel) {
	if l.logLevel <= WARNING {
		l.writeLog("WARNING", message, context, channel)
	}
}

func (l *Logger) Error(message string, context map[string]interface{}, channel Channel) {
	if l.logLevel <= ERROR {
		l.writeLog("ERROR", message, context, channel)
	}
}

func (l *Logger) Critical(message string, context map[string]interface{}, channel Channel) {
	if l.logLevel <= CRITICAL {
		l.writeLog("CRITICAL", message, context, channel)
	}
}

func (l *Logger) writeLog(level, message string, context map[string]interface{}, channel Channel) {
	timestamp := time.Now().Format(time.RFC3339)
	logEntry := timestamp + " [" + level + "] [" + string(channel) + "] " + message
	log.Println(logEntry)
}